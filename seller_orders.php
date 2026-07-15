<?php

require_once __DIR__ . "/init.php";

requireAdmin();

$sellerId = sessionUserId();
$allowedStatuses = ["paid", "packed", "out_delivery", "delivered", "cancelled"];
$viewOrderId = isset($_GET["view"]) ? (int) $_GET["view"] : 0;

function sellerOrderBucket($status)
{
    $status = strtolower(trim((string) $status));

    if ($status === "packed") {
        return "packed";
    }

    if ($status === "out_delivery" || $status === "shipped") {
        return "out_delivery";
    }

    if ($status === "delivered") {
        return "delivered";
    }

    if ($status === "cancelled") {
        return "cancelled";
    }

    return "paid";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken(isset($_POST["csrf_token"]) ? $_POST["csrf_token"] : "")) {
        setFlash("error", "Your session expired. Please try again.");
        redirect("seller_orders.php");
    }

    $orderId = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
    $newStatus = strtolower(trim(isset($_POST["status"]) ? $_POST["status"] : ""));

    if ($orderId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
        setFlash("error", "Please choose a valid order update.");
        redirect("seller_orders.php");
    }

    $checkStmt = mysqli_prepare($conn, "SELECT order_id, status FROM orders WHERE order_id = ? LIMIT 1");
    mysqli_stmt_bind_param($checkStmt, "i", $orderId);
    mysqli_stmt_execute($checkStmt);
    $existingOrder = mysqli_fetch_assoc(mysqli_stmt_get_result($checkStmt));

    if (!$existingOrder) {
        setFlash("error", "Order not found.");
        redirect("seller_orders.php");
    }

    $updateStmt = mysqli_prepare($conn, "UPDATE orders SET status = ?, status_updated_at = NOW() WHERE order_id = ?");
    mysqli_stmt_bind_param($updateStmt, "si", $newStatus, $orderId);
    mysqli_stmt_execute($updateStmt);

    $note = "Seller changed this order to " . orderStatusLabel($newStatus) . ".";
    addOrderHistory($conn, $orderId, $newStatus, $note, $sellerId);
    logAudit($conn, $sellerId, "Update Order", "orders", $orderId, $note);

    setFlash("success", "Order #" . $orderId . " is now " . orderStatusLabel($newStatus) . ".");
    redirect("seller_orders.php#order-" . $orderId);
}

$orderCounts = [
    "paid" => 0,
    "packed" => 0,
    "out_delivery" => 0,
    "delivered" => 0,
    "cancelled" => 0,
];

$countResult = mysqli_query($conn, "SELECT LOWER(COALESCE(status, 'paid')) AS status_key, COUNT(*) AS total FROM orders GROUP BY LOWER(COALESCE(status, 'paid'))");

if ($countResult) {
    while ($countRow = mysqli_fetch_assoc($countResult)) {
        $bucket = sellerOrderBucket($countRow["status_key"]);
        $orderCounts[$bucket] = $orderCounts[$bucket] + (int) $countRow["total"];
    }
}

$search = trim(isset($_GET["q"]) ? $_GET["q"] : "");
$statusFilter = strtolower(trim(isset($_GET["status"]) ? $_GET["status"] : ""));
$sort = strtolower(trim(isset($_GET["sort"]) ? $_GET["sort"] : "newest"));
$orders = [];
$rowsForExport = [];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = "";
}

$sortOptions = [
    "newest" => "o.order_id DESC",
    "oldest" => "o.order_id ASC",
    "amount_desc" => "o.total_amount DESC, o.order_id DESC",
    "amount_asc" => "o.total_amount ASC, o.order_id DESC",
    "updated_desc" => "o.status_updated_at DESC, o.order_id DESC",
];

if (!isset($sortOptions[$sort])) {
    $sort = "newest";
}

$baseSql = "
    SELECT o.order_id, o.user_id, o.total_amount, o.payment_method, o.shipping_address,
           o.status, o.created_at, o.status_updated_at,
           a.firstname, a.middlename, a.lastname, a.email,
           GROUP_CONCAT(CONCAT(oi.quantity, 'x ', p.product_name) SEPARATOR ', ') AS item_summary
    FROM orders o
    LEFT JOIN tblaccount a ON o.user_id = a.id
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.product_id
";

$conditions = [];
$params = [];
$types = "";

if ($search !== "") {
    $like = "%" . $search . "%";
    $conditions[] = "(CAST(o.order_id AS CHAR) LIKE ?
           OR CONCAT(a.firstname, ' ', a.lastname) LIKE ?
           OR a.email LIKE ?
           OR p.product_name LIKE ?
           OR o.payment_method LIKE ?
           OR o.shipping_address LIKE ?
           OR o.status LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sssssss";
}

if ($statusFilter !== "") {
    if ($statusFilter === "paid") {
        $conditions[] = "(o.status IS NULL OR LOWER(o.status) NOT IN ('packed', 'out_delivery', 'shipped', 'delivered', 'cancelled'))";
    } elseif ($statusFilter === "out_delivery") {
        $conditions[] = "LOWER(o.status) IN ('out_delivery', 'shipped')";
    } else {
        $conditions[] = "LOWER(o.status) = ?";
        $params[] = $statusFilter;
        $types .= "s";
    }
}

$whereSql = count($conditions) > 0 ? "WHERE " . implode(" AND ", $conditions) : "";
$orderSql = $baseSql . "
    $whereSql
    GROUP BY o.order_id, o.user_id, o.total_amount, o.payment_method, o.shipping_address,
             o.status, o.created_at, o.status_updated_at,
             a.firstname, a.middlename, a.lastname, a.email
    ORDER BY {$sortOptions[$sort]}
";

if (count($params) > 0) {
    $orderStmt = mysqli_prepare($conn, $orderSql);
    mysqli_stmt_bind_param($orderStmt, $types, ...$params);
    mysqli_stmt_execute($orderStmt);
    $ordersResult = mysqli_stmt_get_result($orderStmt);
} else {
    $ordersResult = mysqli_query($conn, $orderSql);
}

if ($ordersResult) {
    while ($order = mysqli_fetch_assoc($ordersResult)) {
        $bucket = sellerOrderBucket($order["status"]);

        if (!isset($orders[$bucket])) {
            $orders[$bucket] = [];
        }

        $orders[$bucket][] = $order;
        $rowsForExport[] = [
            "order_id" => (int) $order["order_id"],
            "customer" => accountFullName($order),
            "email" => $order["email"],
            "status" => orderStatusLabel($bucket),
            "total" => $order["total_amount"],
            "payment_method" => $order["payment_method"],
            "shipping_address" => $order["shipping_address"],
            "items" => $order["item_summary"] ?: "No items listed",
            "updated_at" => formatDateTimeLabel($order["status_updated_at"]),
        ];
    }
}

$matchingTotal = count($rowsForExport);

function sellerOrderQueryString(array $overrides = [], array $current = []): string
{
    $merged = array_merge($current, $overrides);
    $merged = array_filter($merged, static fn($value) => $value !== "" && $value !== null);
    return http_build_query($merged);
}

$currentParams = [
    "q" => $search,
    "status" => $statusFilter,
    "sort" => $sort === "newest" ? "" : $sort,
];

$viewOrder = null;
$viewItems = [];
$viewHistory = [];

if ($viewOrderId > 0) {
    $viewStmt = mysqli_prepare($conn, "
        SELECT o.order_id, o.user_id, o.total_amount, o.payment_method, o.shipping_address,
               o.status, o.created_at, o.status_updated_at,
               a.firstname, a.middlename, a.lastname, a.email, a.contact, a.address
        FROM orders o
        LEFT JOIN tblaccount a ON o.user_id = a.id
        WHERE o.order_id = ?
        LIMIT 1
    ");
    mysqli_stmt_bind_param($viewStmt, "i", $viewOrderId);
    mysqli_stmt_execute($viewStmt);
    $viewOrder = mysqli_fetch_assoc(mysqli_stmt_get_result($viewStmt));

    if ($viewOrder) {
        $viewItemsStmt = mysqli_prepare($conn, "
            SELECT oi.quantity, oi.price, oi.subtotal, p.product_name
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.product_id
            WHERE oi.order_id = ?
            ORDER BY oi.order_item_id ASC
        ");
        mysqli_stmt_bind_param($viewItemsStmt, "i", $viewOrderId);
        mysqli_stmt_execute($viewItemsStmt);
        $viewItemsResult = mysqli_stmt_get_result($viewItemsStmt);

        while ($item = mysqli_fetch_assoc($viewItemsResult)) {
            $viewItems[] = $item;
        }

        $viewHistoryStmt = mysqli_prepare($conn, "
            SELECT h.status, h.note, h.created_at, a.firstname, a.middlename, a.lastname, a.level
            FROM order_status_history h
            LEFT JOIN tblaccount a ON h.updated_by = a.id
            WHERE h.order_id = ?
            ORDER BY h.created_at DESC, h.history_id DESC
        ");
        mysqli_stmt_bind_param($viewHistoryStmt, "i", $viewOrderId);
        mysqli_stmt_execute($viewHistoryStmt);
        $viewHistoryResult = mysqli_stmt_get_result($viewHistoryStmt);

        while ($history = mysqli_fetch_assoc($viewHistoryResult)) {
            $viewHistory[] = $history;
        }
    }
}

$columns = [
    "paid" => ["title" => "To Pack", "accent" => "warning"],
    "packed" => ["title" => "To Ship", "accent" => "info"],
    "out_delivery" => ["title" => "Out for Delivery", "accent" => "purple"],
    "delivered" => ["title" => "Delivered", "accent" => "success"],
    "cancelled" => ["title" => "Cancelled", "accent" => "danger"],
];

$pageTitle = "Order Management";
require __DIR__ . "/header.php";
?>

<main class="page-main order-management-page">
    <div class="seller-page-header order-page-header">
        <div>
            <div class="eyebrow">Seller Tools</div>
            <h2>Order Management</h2>
            <p>Move buyer orders across each delivery stage. Every update is saved for buyer tracking.</p>
        </div>
        <a class="button secondary" href="seller_dashboard.php">Dashboard</a>
    </div>

    <div class="analytics-grid order-stat-grid">
        <div class="card insight-card order-stat-card warning">
            <span class="panel-label">To Pack</span>
            <strong><?php echo (int) $orderCounts["paid"]; ?></strong>
            <p>Paid orders waiting for packing.</p>
        </div>
        <div class="card insight-card order-stat-card info">
            <span class="panel-label">To Ship</span>
            <strong><?php echo (int) $orderCounts["packed"]; ?></strong>
            <p>Packed orders ready for courier handoff.</p>
        </div>
        <div class="card insight-card order-stat-card purple">
            <span class="panel-label">Out for Delivery</span>
            <strong><?php echo (int) $orderCounts["out_delivery"]; ?></strong>
            <p>Orders currently moving to buyers.</p>
        </div>
        <div class="card insight-card order-stat-card success">
            <span class="panel-label">Delivered</span>
            <strong><?php echo (int) $orderCounts["delivered"]; ?></strong>
            <p>Completed buyer deliveries.</p>
        </div>
        <div class="card insight-card order-stat-card danger">
            <span class="panel-label">Cancelled</span>
            <strong><?php echo (int) $orderCounts["cancelled"]; ?></strong>
            <p>Orders removed from fulfillment.</p>
        </div>
    </div>

    <form class="filter-bar order-search-bar" method="get" action="seller_orders.php">
        <div class="field grow">
            <label for="orderSearch">Search</label>
            <input id="orderSearch" type="search" name="q" value="<?php echo e($search); ?>" placeholder="Order ID, customer, email, product, payment, or address">
        </div>
        <div class="field">
            <label for="orderStatus">Status</label>
            <select id="orderStatus" name="status">
                <option value="">All statuses</option>
                <?php foreach ($columns as $statusKey => $column): ?>
                    <option value="<?php echo e($statusKey); ?>" <?php echo $statusFilter === $statusKey ? "selected" : ""; ?>><?php echo e($column["title"]); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="orderSort">Sort</label>
            <select id="orderSort" name="sort">
                <option value="newest" <?php echo $sort === "newest" ? "selected" : ""; ?>>Newest first</option>
                <option value="oldest" <?php echo $sort === "oldest" ? "selected" : ""; ?>>Oldest first</option>
                <option value="updated_desc" <?php echo $sort === "updated_desc" ? "selected" : ""; ?>>Recently updated</option>
                <option value="amount_desc" <?php echo $sort === "amount_desc" ? "selected" : ""; ?>>Highest total</option>
                <option value="amount_asc" <?php echo $sort === "amount_asc" ? "selected" : ""; ?>>Lowest total</option>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit">Apply</button>
            <?php if ($search !== "" || $statusFilter !== "" || $sort !== "newest"): ?>
                <a class="button secondary" href="seller_orders.php">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="seller-order-control-card">
        <div class="seller-order-toolbar">
            <p><?php echo $matchingTotal; ?> order<?php echo $matchingTotal === 1 ? "" : "s"; ?> match your current view.</p>
            <div class="seller-order-tools">
                <label class="inline-filter compact-toggle"><input id="orderCompactToggle" type="checkbox"> Compact cards</label>
                <button type="button" id="orderExportBtn" class="button secondary">Export CSV</button>
            </div>
        </div>

        <div class="quick-filter-row seller-order-quick-filters">
            <a class="<?php echo $statusFilter === "" ? "active" : ""; ?>" href="seller_orders.php?<?php echo sellerOrderQueryString(["status" => ""], $currentParams); ?>">All</a>
            <?php foreach ($columns as $statusKey => $column): ?>
                <a class="<?php echo $statusFilter === $statusKey ? "active" : ""; ?>" href="seller_orders.php?<?php echo sellerOrderQueryString(["status" => $statusKey], $currentParams); ?>">
                    <?php echo e($column["title"]); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <section class="seller-order-board">
        <?php foreach ($columns as $statusKey => $column): ?>
            <div class="seller-order-column <?php echo e($column["accent"]); ?>">
                <div class="seller-order-column-head">
                    <h2><?php echo e($column["title"]); ?></h2>
                    <span><?php echo isset($orders[$statusKey]) ? count($orders[$statusKey]) : 0; ?></span>
                </div>

                <?php if (!empty($orders[$statusKey])): ?>
                    <?php foreach ($orders[$statusKey] as $order): ?>
                        <?php
                            $status = sellerOrderBucket($order["status"]);
                            $customerName = accountFullName($order);
                            $itemSummary = $order["item_summary"] ? $order["item_summary"] : "No items listed";
                        ?>
                        <article id="order-<?php echo (int) $order["order_id"]; ?>" class="seller-order-card">
                            <div class="seller-order-card-top">
                                <strong>#HN-<?php echo (int) $order["order_id"]; ?></strong>
                                <span><?php echo formatPrice($order["total_amount"]); ?></span>
                            </div>
                            <p class="meta">Updated <?php echo e(formatDateTimeLabel($order["status_updated_at"])); ?></p>
                            <h3><?php echo e($customerName); ?></h3>
                            <p class="muted"><?php echo e($itemSummary); ?></p>
                            <p class="meta"><?php echo e($order["payment_method"]); ?> - <?php echo e($order["shipping_address"]); ?></p>

                            <div class="seller-order-actions">
                                <?php if ($status === "paid"): ?>
                                    <form method="post" action="seller_orders.php">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="order_id" value="<?php echo (int) $order["order_id"]; ?>">
                                        <input type="hidden" name="status" value="packed">
                                        <button type="submit">Mark as Packed</button>
                                    </form>
                                <?php elseif ($status === "packed"): ?>
                                    <form method="post" action="seller_orders.php">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="order_id" value="<?php echo (int) $order["order_id"]; ?>">
                                        <input type="hidden" name="status" value="out_delivery">
                                        <button type="submit">Hand to Courier</button>
                                    </form>
                                <?php elseif ($status === "out_delivery"): ?>
                                    <form method="post" action="seller_orders.php">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="order_id" value="<?php echo (int) $order["order_id"]; ?>">
                                        <input type="hidden" name="status" value="delivered">
                                        <button type="submit">Mark Delivered</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($status !== "delivered" && $status !== "cancelled"): ?>
                                    <form method="post" action="seller_orders.php">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="order_id" value="<?php echo (int) $order["order_id"]; ?>">
                                        <input type="hidden" name="status" value="cancelled">
                                        <button class="secondary" type="submit">Cancel</button>
                                    </form>
                                <?php endif; ?>

                                <button class="order-view-toggle" type="button" aria-expanded="false">View</button>
                            </div>
                            <div class="seller-order-inline-detail" hidden>
                                <div>
                                    <span class="panel-label">Customer</span>
                                    <strong><?php echo e($customerName); ?></strong>
                                    <p><?php echo e($order["email"]); ?></p>
                                </div>
                                <div>
                                    <span class="panel-label">Items</span>
                                    <p><?php echo e($itemSummary); ?></p>
                                </div>
                                <div>
                                    <span class="panel-label">Delivery</span>
                                    <p><?php echo e($order["shipping_address"]); ?></p>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="seller-order-empty">No orders in this stage.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </section>
</main>

<script>
const orderExportRows = <?php echo json_encode($rowsForExport, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
const orderExportBtn = document.getElementById("orderExportBtn");
const orderCompactToggle = document.getElementById("orderCompactToggle");
const orderBoard = document.querySelector(".seller-order-board");
const orderFilterForm = document.querySelector(".order-search-bar");
const orderStatusFilter = document.getElementById("orderStatus");
const orderSortFilter = document.getElementById("orderSort");

if (orderCompactToggle && orderBoard) {
    orderCompactToggle.addEventListener("change", () => {
        orderBoard.classList.toggle("is-compact", orderCompactToggle.checked);
    });
}

document.querySelectorAll(".order-view-toggle").forEach((button) => {
    button.addEventListener("click", () => {
        const card = button.closest(".seller-order-card");
        const detail = card ? card.querySelector(".seller-order-inline-detail") : null;

        if (!detail) {
            return;
        }

        const isOpening = detail.hidden;
        detail.hidden = !isOpening;
        button.setAttribute("aria-expanded", isOpening ? "true" : "false");
        button.textContent = isOpening ? "Hide" : "View";
        card.classList.toggle("is-expanded", isOpening);
    });
});

[orderStatusFilter, orderSortFilter].forEach((control) => {
    if (control && orderFilterForm) {
        control.addEventListener("change", () => {
            orderFilterForm.requestSubmit();
        });
    }
});

if (orderExportBtn) {
    orderExportBtn.addEventListener("click", () => {
        const header = ["Order ID", "Customer", "Email", "Status", "Total", "Payment", "Shipping Address", "Items", "Updated"];
        const lines = [header.join(",")];

        orderExportRows.forEach((row) => {
            const cells = [
                row.order_id,
                row.customer,
                row.email,
                row.status,
                row.total,
                row.payment_method,
                row.shipping_address,
                row.items,
                row.updated_at,
            ].map((value) => {
                const text = String(value ?? "");
                return /[",\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
            });
            lines.push(cells.join(","));
        });

        const blob = new Blob([lines.join("\n")], { type: "text/csv;charset=utf-8;" });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = "seller-orders-export.csv";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    });
}
</script>

<?php require __DIR__ . "/footer.php"; ?>
