<?php

require_once __DIR__ . "/init.php";

requireLogin();

$userId = sessionUserId();
$pendingCheckout = $_SESSION["pending_checkout"] ?? null;

if (!$pendingCheckout) {
    setFlash("error", "Please complete checkout details first.");
    redirect("checkout.php");
}

$items = cartItems($conn, $userId);

if (count($items) === 0) {
    unset($_SESSION["pending_checkout"]);
    setFlash("error", "Your cart is empty.");
    redirect("cart.php");
}

$message = "";
$total = cartTotal($conn, $userId);
$itemCount = array_sum(array_map(function ($item) {
    return (int) $item["quantity"];
}, $items));

function paymentTableExists($conn, $tableName)
{
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS table_count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "s", $tableName);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    return (int) ($row["table_count"] ?? 0) > 0;
}

function paymentColumnExists($conn, $tableName, $columnName)
{
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS column_count FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "ss", $tableName, $columnName);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    return (int) ($row["column_count"] ?? 0) > 0;
}

function paymentPrepare($conn, $sql)
{
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new Exception("Payment could not be processed. Please check the database setup and try again.");
    }

    return $stmt;
}

function paymentEnsureOrderTrackingSchema($conn)
{
    if (!paymentColumnExists($conn, "orders", "status_updated_at")) {
        if (!mysqli_query($conn, "ALTER TABLE orders ADD COLUMN status_updated_at datetime DEFAULT NULL AFTER status")) {
            throw new Exception("Payment could not update the order tracking setup. Please check the database setup and try again.");
        }
    }

    if (!paymentTableExists($conn, "order_status_history")) {
        $historySql = "
            CREATE TABLE order_status_history (
                history_id int(11) NOT NULL AUTO_INCREMENT,
                order_id int(11) DEFAULT NULL,
                status varchar(20) DEFAULT NULL,
                note varchar(255) DEFAULT NULL,
                updated_by int(11) DEFAULT NULL,
                created_at datetime DEFAULT NULL,
                PRIMARY KEY (history_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ";

        if (!mysqli_query($conn, $historySql)) {
            throw new Exception("Payment could not create the order history setup. Please check the database setup and try again.");
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        $message = "Your session expired. Please try again.";
    } else {
        $transactionStarted = false;

        try {
            if (!@mysqli_begin_transaction($conn)) {
                throw new Exception("Payment could not start. Please try again.");
            }

            $transactionStarted = true;

            foreach ($items as $item) {
                if (strtolower((string) $item["status"]) !== "active" || (int) $item["quantity"] > (int) $item["stock_quantity"]) {
                    throw new Exception("One or more cart items are unavailable or above stock.");
                }
            }

            $status = "paid";
            paymentEnsureOrderTrackingSchema($conn);
            $hasStatusUpdatedAt = paymentColumnExists($conn, "orders", "status_updated_at");

            if ($hasStatusUpdatedAt) {
                $stmt = paymentPrepare($conn, "INSERT INTO orders (user_id, total_amount, payment_method, shipping_address, status, status_updated_at, created_at) VALUES (?, ?, ?, ?, ?, NOW(), CURDATE())");
                mysqli_stmt_bind_param($stmt, "idsss", $userId, $total, $pendingCheckout["payment_method"], $pendingCheckout["shipping_address"], $status);
            } else {
                $stmt = paymentPrepare($conn, "INSERT INTO orders (user_id, total_amount, payment_method, shipping_address, status, created_at) VALUES (?, ?, ?, ?, ?, CURDATE())");
                mysqli_stmt_bind_param($stmt, "idsss", $userId, $total, $pendingCheckout["payment_method"], $pendingCheckout["shipping_address"], $status);
            }

            mysqli_stmt_execute($stmt);
            $orderId = mysqli_insert_id($conn);

            foreach ($items as $item) {
                $quantity = (int) $item["quantity"];
                $price = (float) $item["price"];
                $subtotal = $price * $quantity;
                $productId = (int) $item["product_id"];

                $insertItem = paymentPrepare($conn, "INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($insertItem, "iiidd", $orderId, $productId, $quantity, $price, $subtotal);
                mysqli_stmt_execute($insertItem);

                $stockUpdate = paymentPrepare($conn, "UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ? AND stock_quantity >= ?");
                mysqli_stmt_bind_param($stockUpdate, "iii", $quantity, $productId, $quantity);
                mysqli_stmt_execute($stockUpdate);

                if (mysqli_stmt_affected_rows($stockUpdate) !== 1) {
                    throw new Exception("Stock changed while processing payment. Please review your cart.");
                }
            }

            cartClear($conn, $userId);

            if (paymentTableExists($conn, "order_status_history")) {
                addOrderHistory($conn, $orderId, $status, "Buyer placed this order.", $userId);
            }

            logAudit($conn, $userId, "Create Order", "orders", $orderId, "Created simulated payment order #" . $orderId);

            mysqli_commit($conn);
            unset($_SESSION["pending_checkout"]);

            setFlash("success", "Payment simulated successfully. Order #" . $orderId . " has been created.");
            redirect("buyer_orders.php?id=" . (int) $orderId);
        } catch (Throwable $e) {
            if ($transactionStarted) {
                mysqli_rollback($conn);
            }

            $message = $e->getMessage();
        }
    }
}

$pageTitle = "Payment";
require __DIR__ . "/header.php";
?>

<main class="page-main payment-page-main">
    <section class="buyer-page-header payment-page-header">
        <div>
            <span class="buyer-kicker">Buyer Checkout</span>
            <h2>Payment</h2>
            <p>Review your checkout details before HealthNest creates the order and reserves stock.</p>
        </div>
        <a class="button secondary" href="checkout.php">Edit Details</a>
    </section>

    <?php if ($message !== ""): ?>
        <div class="error"><?php echo e($message); ?></div>
    <?php endif; ?>

    <section class="payment-review-grid">
        <article class="card payment-review-card">
            <span class="panel-label">Order Review</span>
            <h3>Ready to Confirm</h3>

            <div class="payment-total-card">
                <span>Total Amount</span>
                <strong><?php echo formatPrice($total); ?></strong>
                <p><?php echo $itemCount; ?> unit(s) across <?php echo count($items); ?> line item(s)</p>
            </div>

            <div class="payment-detail-list">
                <div>
                    <span>Payment Method</span>
                    <strong><?php echo e($pendingCheckout["payment_method"]); ?></strong>
                </div>
                <div>
                    <span>Shipping Address</span>
                    <strong><?php echo e($pendingCheckout["shipping_address"]); ?></strong>
                </div>
            </div>

            <div class="buyer-info-note">
                <strong>Before confirming</strong>
                <p>Stock will be checked again, the order will be created, and your cart will be cleared after successful confirmation.</p>
            </div>
        </article>

        <form method="post" action="payment.php" class="buyer-checkout-form payment-confirm-card">
            <?php echo csrfField(); ?>
            <span class="panel-label">Confirmation</span>
            <h3>Complete Payment</h3>
            <ul class="quick-list buyer-checklist">
                <li>Order total reviewed</li>
                <li>Shipping address confirmed</li>
                <li>Stock reservation happens on submit</li>
            </ul>
            <div class="form-actions">
                <button type="submit">Confirm Payment</button>
                <a href="checkout.php">Back to Checkout</a>
            </div>
        </form>
    </section>
</main>

<?php require __DIR__ . "/footer.php"; ?>
