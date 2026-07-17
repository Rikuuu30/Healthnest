<?php

require_once __DIR__ . "/init.php";

requireLogin();

if (isAdmin()) {
    redirect("seller_orders.php");
}

$userId = sessionUserId();
$selectedOrderId = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken(isset($_POST["csrf_token"]) ? $_POST["csrf_token"] : "")) {
        setFlash("error", "Your session expired. Please try again.");
        redirect("buyer_orders.php");
    }

    $cancelOrderId = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;

    $cancelStmt = mysqli_prepare($conn, "SELECT order_id, status FROM orders WHERE order_id = ? AND user_id = ? LIMIT 1");
    mysqli_stmt_bind_param($cancelStmt, "ii", $cancelOrderId, $userId);
    mysqli_stmt_execute($cancelStmt);
    $cancelOrder = mysqli_fetch_assoc(mysqli_stmt_get_result($cancelStmt));

    if (!$cancelOrder) {
        setFlash("error", "Order not found.");
        redirect("buyer_orders.php");
    }

    $currentStatus = strtolower(trim((string) $cancelOrder["status"]));

    if ($currentStatus === "delivered" || $currentStatus === "cancelled") {
        setFlash("error", "This order can no longer be cancelled.");
        redirect("buyer_orders.php?id=" . $cancelOrderId);
    }

    $newStatus = "cancelled";
    $updateStmt = mysqli_prepare($conn, "UPDATE orders SET status = ?, status_updated_at = NOW() WHERE order_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($updateStmt, "sii", $newStatus, $cancelOrderId, $userId);
    mysqli_stmt_execute($updateStmt);

    addOrderHistory($conn, $cancelOrderId, $newStatus, "Buyer cancelled this order.", $userId);
    logAudit($conn, $userId, "Cancel Order", "orders", $cancelOrderId, "Buyer cancelled order #" . $cancelOrderId);

    setFlash("success", "Order #" . $cancelOrderId . " was cancelled.");
    redirect("buyer_orders.php?id=" . $cancelOrderId);
}

if ($selectedOrderId > 0) {
    $orderStmt = mysqli_prepare($conn, "SELECT * FROM orders WHERE order_id = ? AND user_id = ? LIMIT 1");
    mysqli_stmt_bind_param($orderStmt, "ii", $selectedOrderId, $userId);
} else {
    $orderStmt = mysqli_prepare($conn, "
        SELECT *
        FROM orders
        WHERE user_id = ?
        ORDER BY CASE WHEN LOWER(COALESCE(status, 'paid')) IN ('delivered', 'cancelled') THEN 1 ELSE 0 END, order_id DESC
        LIMIT 1
    ");
    mysqli_stmt_bind_param($orderStmt, "i", $userId);
}

mysqli_stmt_execute($orderStmt);
$currentOrder = mysqli_fetch_assoc(mysqli_stmt_get_result($orderStmt));

$orderItems = [];
$historyRows = [];
$allOrders = [];
$historyByStatus = [];
$latestHistory = null;
$orderSummaryResult = mysqli_query($conn, "
    SELECT
        COUNT(*) AS total_orders,
        SUM(CASE WHEN LOWER(COALESCE(status, 'paid')) NOT IN ('delivered', 'cancelled') THEN 1 ELSE 0 END) AS active_orders,
        SUM(CASE WHEN LOWER(COALESCE(status, 'paid')) = 'delivered' THEN 1 ELSE 0 END) AS delivered_orders,
        SUM(CASE WHEN LOWER(COALESCE(status, 'paid')) = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders
    FROM orders
    WHERE user_id = " . (int) $userId . "
");
$orderSummary = $orderSummaryResult ? mysqli_fetch_assoc($orderSummaryResult) : [];
$totalOrderCount = (int) ($orderSummary["total_orders"] ?? 0);
$activeOrderCount = (int) ($orderSummary["active_orders"] ?? 0);
$deliveredOrderCount = (int) ($orderSummary["delivered_orders"] ?? 0);
$cancelledOrderCount = (int) ($orderSummary["cancelled_orders"] ?? 0);

function buyerOrderPriceHtml($amount)
{
    return '<span class="buyer-price"><span class="buyer-price-sign">PHP</span> <span class="buyer-price-amount">' . e(number_format((float) $amount, 2)) . '</span></span>';
}

if ($currentOrder) {
    $selectedOrderId = (int) $currentOrder["order_id"];

    $itemsStmt = mysqli_prepare($conn, "
        SELECT oi.quantity, oi.price, oi.subtotal, p.product_name, p.image
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.product_id
        WHERE oi.order_id = ?
        ORDER BY oi.order_item_id ASC
    ");
    mysqli_stmt_bind_param($itemsStmt, "i", $selectedOrderId);
    mysqli_stmt_execute($itemsStmt);
    $itemsResult = mysqli_stmt_get_result($itemsStmt);

    while ($item = mysqli_fetch_assoc($itemsResult)) {
        $orderItems[] = $item;
    }

    $historyStmt = mysqli_prepare($conn, "
        SELECT h.status, h.note, h.created_at, h.updated_by, a.level
        FROM order_status_history h
        LEFT JOIN tblaccount a ON h.updated_by = a.id
        WHERE h.order_id = ?
        ORDER BY h.created_at DESC, h.history_id DESC
    ");
    mysqli_stmt_bind_param($historyStmt, "i", $selectedOrderId);
    mysqli_stmt_execute($historyStmt);
    $historyResult = mysqli_stmt_get_result($historyStmt);

    while ($history = mysqli_fetch_assoc($historyResult)) {
        if (!$latestHistory) {
            $latestHistory = $history;
        }

        $historyRows[] = $history;
        $historyStatus = strtolower(trim((string) $history["status"]));

        if (!isset($historyByStatus[$historyStatus])) {
            $historyByStatus[$historyStatus] = $history["created_at"];
        }
    }

    $allOrdersStmt = mysqli_prepare($conn, "
        SELECT order_id, total_amount, status, created_at
        FROM orders
        WHERE user_id = ?
        ORDER BY order_id DESC
    ");
    mysqli_stmt_bind_param($allOrdersStmt, "i", $userId);
    mysqli_stmt_execute($allOrdersStmt);
    $allOrdersResult = mysqli_stmt_get_result($allOrdersStmt);

    while ($orderRow = mysqli_fetch_assoc($allOrdersResult)) {
        $allOrders[] = $orderRow;
    }
}

$pageTitle = "My Orders";
require __DIR__ . "/header.php";
?>

<main class="page-main buyer-tracking-page">
    <section class="buyer-tracking-hero">
        <div>
            <div class="eyebrow">Buyer Orders</div>
            <h1>Track Your Order</h1>
            <p class="lead">Your order status changes here when the seller updates packing, courier, or delivery progress.</p>
        </div>
        <a class="button secondary" href="profile.php">Back to My Profile</a>
    </section>

    <section class="dashboard-stat-strip buyer-stat-strip">
        <div>
            <span>Total Orders</span>
            <strong><?php echo $totalOrderCount; ?></strong>
        </div>
        <div>
            <span>Active</span>
            <strong><?php echo $activeOrderCount; ?></strong>
        </div>
        <div>
            <span>Delivered</span>
            <strong><?php echo $deliveredOrderCount; ?></strong>
        </div>
        <div>
            <span>Cancelled</span>
            <strong><?php echo $cancelledOrderCount; ?></strong>
        </div>
    </section>

    <?php if (!$currentOrder): ?>
        <section class="card buyer-tracking-empty">
            <h2>No orders yet</h2>
            <p class="muted">Your tracking details will appear after checkout.</p>
            <div class="actions">
                <a href="products.php">Shop Products</a>
            </div>
        </section>
    <?php else: ?>
        <?php
            $currentStatus = strtolower(trim((string) $currentOrder["status"]));
            $currentStep = orderStatusStep($currentStatus);
            $canCancel = $currentStatus !== "delivered" && $currentStatus !== "cancelled";
            $firstItemName = count($orderItems) > 0 ? $orderItems[0]["product_name"] : "Order items";
            $nextActionText = $currentStatus === "delivered"
                ? "Order delivered. You can shop the catalog again or review similar products."
                : ($currentStatus === "cancelled"
                    ? "This order is cancelled. Browse available products to place a new order."
                    : "Keep this order number handy. Seller updates will appear in the timeline.");
            $timeline = [
                ["status" => "paid", "label" => "Order Placed"],
                ["status" => "packed", "label" => "Packed"],
                ["status" => "shipped", "label" => "Shipped"],
                ["status" => "out_delivery", "label" => "Out for Delivery"],
                ["status" => "delivered", "label" => "Delivered"],
            ];
        ?>

        <section class="tracking-live-note">
            <span></span>
            <?php if ($latestHistory): ?>
                <strong>Live tracking - last update: <?php echo e(formatDateTimeLabel($latestHistory["created_at"])); ?>.</strong>
            <?php else: ?>
                <strong>Live tracking - waiting for the first seller update.</strong>
            <?php endif; ?>
        </section>

        <div class="buyer-order-layout">
            <div class="buyer-order-main">
                <section class="buyer-order-card">
                    <div class="buyer-order-card-head">
                        <div>
                            <span class="panel-label">Order Tracking</span>
                            <h2>Current Order</h2>
                        </div>
                        <span class="order-status-pill <?php echo e(orderStatusClass($currentStatus)); ?>"><?php echo e(orderStatusLabel($currentStatus)); ?></span>
                    </div>

                    <div class="buyer-current-order-summary">
                        <div>
                            <strong>Order #HN-<?php echo (int) $currentOrder["order_id"]; ?></strong>
                            <span>Placed on <?php echo e($currentOrder["created_at"]); ?></span>
                        </div>
                        <p><?php echo e($firstItemName); ?></p>
                        <strong class="buyer-order-total"><?php echo buyerOrderPriceHtml($currentOrder["total_amount"]); ?></strong>
                        <button class="secondary buyer-copy-order" type="button" data-copy-order="HN-<?php echo (int) $currentOrder["order_id"]; ?>">Copy Order No.</button>
                    </div>

                    <div class="tracking-timeline">
                        <?php foreach ($timeline as $index => $step): ?>
                            <?php
                                $stepNumber = $index + 1;
                                $stepClass = "pending";

                                if ($currentStatus === "cancelled") {
                                    $stepClass = $stepNumber === 1 ? "current" : "pending";
                                } elseif ($currentStep > $stepNumber) {
                                    $stepClass = "complete";
                                } elseif ($currentStep === $stepNumber) {
                                    $stepClass = "current";
                                }

                                $stepTime = isset($historyByStatus[$step["status"]]) ? formatDateTimeLabel($historyByStatus[$step["status"]]) : "Pending";

                                if ($step["status"] === "shipped" && $stepTime === "Pending" && isset($historyByStatus["out_delivery"])) {
                                    $stepTime = formatDateTimeLabel($historyByStatus["out_delivery"]);
                                }
                            ?>
                            <div class="tracking-step <?php echo e($stepClass); ?>">
                                <span><?php echo $stepClass === "complete" ? "&#10003;" : $stepNumber; ?></span>
                                <strong><?php echo e($step["label"]); ?></strong>
                                <em><?php echo e($stepTime); ?></em>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="buyer-order-actions">
                        <a href="products.php">Shop Again</a>
                        <?php if ($canCancel): ?>
                            <form method="post" action="buyer_orders.php?id=<?php echo (int) $currentOrder["order_id"]; ?>">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="order_id" value="<?php echo (int) $currentOrder["order_id"]; ?>">
                                <button class="secondary" type="submit">Cancel Order</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="buyer-order-card">
                    <span class="panel-label">Order Activity</span>
                    <h2>Status History</h2>

                    <?php if (count($historyRows) > 0): ?>
                        <ul class="tracking-history-list">
                            <?php foreach ($historyRows as $history): ?>
                                <?php $updatedBySeller = accountLevelValue($history["level"]) === "seller"; ?>
                                <li>
                                    <span class="history-dot <?php echo $updatedBySeller ? "seller" : "buyer"; ?>"></span>
                                    <div>
                                        <strong><?php echo e($history["note"]); ?></strong>
                                        <p class="meta"><?php echo e(formatDateTimeLabel($history["created_at"])); ?></p>
                                    </div>
                                    <em><?php echo $updatedBySeller ? "Seller Update" : "Buyer Update"; ?></em>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="muted">No tracking history yet.</p>
                    <?php endif; ?>
                </section>
                <section class="buyer-order-card">
                    <span class="panel-label">Buyer Guidance</span>
                    <h2>Next Best Action</h2>
                    <p><?php echo e($nextActionText); ?></p>
                    <div class="buyer-chip-row">
                        <a href="products.php">Shop Catalog</a>
                        <a href="cart.php">View Cart</a>
                        <a href="profile.php">Delivery Profile</a>
                    </div>
                </section>
            </div>

            <aside class="buyer-orders-sidebar">
                <span class="panel-label">Order History</span>
                <h2>Your Orders</h2>

                <?php if (count($allOrders) > 0): ?>
                    <div class="buyer-other-orders">
                        <?php foreach ($allOrders as $orderRow): ?>
                            <a class="<?php echo (int) $orderRow["order_id"] === (int) $currentOrder["order_id"] ? "active" : ""; ?>" href="buyer_orders.php?id=<?php echo (int) $orderRow["order_id"]; ?>">
                                <span>#HN-<?php echo (int) $orderRow["order_id"]; ?></span>
                                <em><?php echo buyerOrderPriceHtml($orderRow["total_amount"]); ?></em>
                                <strong class="<?php echo e(orderStatusClass($orderRow["status"])); ?>"><?php echo e(orderStatusLabel($orderRow["status"])); ?></strong>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="muted">No past orders yet.</p>
                <?php endif; ?>
            </aside>
        </div>
    <?php endif; ?>
</main>

<script>
(() => {
    const copyButton = document.querySelector("[data-copy-order]");
    if (!copyButton) {
        return;
    }

    copyButton.addEventListener("click", async () => {
        const orderNumber = copyButton.dataset.copyOrder;
        try {
            await navigator.clipboard.writeText(orderNumber);
            copyButton.textContent = "Copied";
            window.setTimeout(() => {
                copyButton.textContent = "Copy Order No.";
            }, 1400);
        } catch (error) {
            copyButton.textContent = orderNumber;
        }
    });
})();
</script>

<?php require __DIR__ . "/footer.php"; ?>
