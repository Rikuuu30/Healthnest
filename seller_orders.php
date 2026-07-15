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
    redirect("seller_orders.php?view=" . $orderId);
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
$orders = [];

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

if ($search !== "") {
    $like = "%" . $search . "%";
    $orderSql = $baseSql . "
        WHERE CAST(o.order_id AS CHAR) LIKE ?
           OR CONCAT(a.firstname, ' ', a.lastname) LIKE ?
           OR p.product_name LIKE ?
           OR o.status LIKE ?
        GROUP BY o.order_id, o.user_id, o.total_amount, o.payment_method, o.shipping_address,
                 o.status, o.created_at, o.status_updated_at,
                 a.firstname, a.middlename, a.lastname, a.email
        ORDER BY o.order_id DESC
    ";
    $orderStmt = mysqli_prepare($conn, $orderSql);
    mysqli_stmt_bind_param($orderStmt, "ssss", $like, $like, $like, $like);
    mysqli_stmt_execute($orderStmt);
    $ordersResult = mysqli_stmt_get_result($orderStmt);
} else {
    $ordersResult = mysqli_query($conn, $baseSql . "
        GROUP BY o.order_id, o.user_id, o.total_amount, o.payment_method, o.shipping_address,
                 o.status, o.created_at, o.status_updated_at,
                 a.firstname, a.middlename, a.lastname, a.email
        ORDER BY o.order_id DESC
    ");
}

if ($ordersResult) {
    while ($order = mysqli_fetch_assoc($ordersResult)) {
        $bucket = sellerOrderBucket($order["status"]);

        if (!isset($orders[$bucket])) {
            $orders[$bucket] = [];
        }

        $orders[$bucket][] = $order;
    }
}

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
    <section class="seller-hero order-hero">
        <div>
            <div class="eyebrow">Order Fulfillment</div>
            <h1>Order Management</h1>
            <p class="lead">Move buyer orders across each delivery stage. Every update is saved for buyer tracking.</p>
        </div>
        <div class="seller-hero-actions">
            <a class="button secondary" href="seller_dashboard.php">Dashboard</a>
        </div>
    </section>

    <section class="order-stat-grid">
        <div class="order-stat-card warning">
            <strong><?php echo (int) $orderCounts["paid"]; ?></strong>
            <span>To Pack</span>
        </div>
        <div class="order-stat-card info">
            <strong><?php echo (int) $orderCounts["packed"]; ?></strong>
            <span>To Ship</span>
        </div>
        <div class="order-stat-card purple">
            <strong><?php echo (int) $orderCounts["out_delivery"]; ?></strong>
            <span>Out for Delivery</span>
        </div>
        <div class="order-stat-card success">
            <strong><?php echo (int) $orderCounts["delivered"]; ?></strong>
            <span>Delivered</span>
        </div>
        <div class="order-stat-card danger">
            <strong><?php echo (int) $orderCounts["cancelled"]; ?></strong>
            <span>Cancelled</span>
        </div>
    </section>

    <form class="order-search-bar" method="get" action="seller_orders.php">
        <input type="search" name="q" value="<?php echo e($search); ?>" placeholder="Search by order ID, customer, product, or status">
        <button type="submit">Search</button>
        <?php if ($search !== ""): ?>
            <a href="seller_orders.php">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($viewOrder): ?>
        <?php
            $viewStatus = sellerOrderBucket($viewOrder["status"]);
            $viewCustomer = accountFullName($viewOrder);
        ?>
        <section class="seller-order-detail-panel">
            <div class="seller-order-detail-head">
                <div>
                    <span class="panel-label">Order Details</span>
                    <h2>#HN-<?php echo (int) $viewOrder["order_id"]; ?></h2>
                    <p class="muted">Placed <?php echo e($viewOrder["created_at"]); ?> - <?php echo e(orderStatusLabel($viewOrder["status"])); ?></p>
                </div>
                <a href="seller_orders.php">Close</a>
            </div>

            <div class="seller-order-detail-grid">
                <div class="seller-order-detail-card">
                    <span class="panel-label">Customer</span>
                    <h3><?php echo e($viewCustomer); ?></h3>
                    <p><?php echo e($viewOrder["email"]); ?></p>
                    <p><?php echo e($viewOrder["contact"]); ?></p>
                    <p class="muted"><?php echo e($viewOrder["shipping_address"] ?: $viewOrder["address"]); ?></p>
                </div>

                <div class="seller-order-detail-card">
                    <span class="panel-label">Payment</span>
                    <h3><?php echo formatPrice($viewOrder["total_amount"]); ?></h3>
                    <p><?php echo e($viewOrder["payment_method"]); ?></p>
                    <p class="muted">Updated <?php echo e(formatDateTimeLabel($viewOrder["status_updated_at"])); ?></p>
                </div>

                <div class="seller-order-detail-card">
                    <span class="panel-label">Items</span>
                    <?php if (count($viewItems) > 0): ?>
                        <ul class="seller-detail-items">
                            <?php foreach ($viewItems as $item): ?>
                                <li>
                                    <span><?php echo (int) $item["quantity"]; ?>x <?php echo e($item["product_name"]); ?></span>
                                    <strong><?php echo formatPrice($item["subtotal"]); ?></strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="muted">No items listed.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="seller-detail-actions">
                <?php if ($viewStatus === "paid"): ?>
                    <form method="post" action="seller_orders.php">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="order_id" value="<?php echo (int) $viewOrder["order_id"]; ?>">
                        <input type="hidden" name="status" value="packed">
                        <button type="submit">Mark as Packed</button>
                    </form>
                <?php elseif ($viewStatus === "packed"): ?>
                    <form method="post" action="seller_orders.php">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="order_id" value="<?php echo (int) $viewOrder["order_id"]; ?>">
                        <input type="hidden" name="status" value="out_delivery">
                        <button type="submit">Hand to Courier</button>
                    </form>
                <?php elseif ($viewStatus === "out_delivery"): ?>
                    <form method="post" action="seller_orders.php">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="order_id" value="<?php echo (int) $viewOrder["order_id"]; ?>">
                        <input type="hidden" name="status" value="delivered">
                        <button type="submit">Mark Delivered</button>
                    </form>
                <?php endif; ?>

                <?php if ($viewStatus !== "delivered" && $viewStatus !== "cancelled"): ?>
                    <form method="post" action="seller_orders.php">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="order_id" value="<?php echo (int) $viewOrder["order_id"]; ?>">
                        <input type="hidden" name="status" value="cancelled">
                        <button class="secondary" type="submit">Cancel Order</button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (count($viewHistory) > 0): ?>
                <div class="seller-detail-history">
                    <span class="panel-label">Status Updates</span>
                    <?php foreach ($viewHistory as $history): ?>
                        <div>
                            <strong><?php echo e($history["note"]); ?></strong>
                            <p class="meta"><?php echo e(formatDateTimeLabel($history["created_at"])); ?> - <?php echo e(accountFullName($history)); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php elseif ($viewOrderId > 0): ?>
        <div class="error">Order details were not found.</div>
    <?php endif; ?>

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
                        <article class="seller-order-card">
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

                                <?php if ($status !== "delivered"): ?>
                                    <form method="post" action="seller_orders.php">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="order_id" value="<?php echo (int) $order["order_id"]; ?>">
                                        <input type="hidden" name="status" value="cancelled">
                                        <button class="secondary" type="submit">Cancel</button>
                                    </form>
                                <?php endif; ?>

                                <a href="seller_orders.php?view=<?php echo (int) $order["order_id"]; ?>">View</a>
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

<?php require __DIR__ . "/footer.php"; ?>
