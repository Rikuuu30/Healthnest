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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        $message = "Your session expired. Please try again.";
    } else {
        mysqli_begin_transaction($conn);

        try {
            foreach ($items as $item) {
                if (strtolower((string) $item["status"]) !== "active" || (int) $item["quantity"] > (int) $item["stock_quantity"]) {
                    throw new Exception("One or more cart items are unavailable or above stock.");
                }
            }

            $status = "paid";
            $stmt = mysqli_prepare($conn, "INSERT INTO orders (user_id, total_amount, payment_method, shipping_address, status, created_at) VALUES (?, ?, ?, ?, ?, CURDATE())");
            mysqli_stmt_bind_param($stmt, "idsss", $userId, $total, $pendingCheckout["payment_method"], $pendingCheckout["shipping_address"], $status);
            mysqli_stmt_execute($stmt);
            $orderId = mysqli_insert_id($conn);

            foreach ($items as $item) {
                $quantity = (int) $item["quantity"];
                $price = (float) $item["price"];
                $subtotal = $price * $quantity;
                $productId = (int) $item["product_id"];

                $insertItem = mysqli_prepare($conn, "INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($insertItem, "iiidd", $orderId, $productId, $quantity, $price, $subtotal);
                mysqli_stmt_execute($insertItem);

                $stockUpdate = mysqli_prepare($conn, "UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ? AND stock_quantity >= ?");
                mysqli_stmt_bind_param($stockUpdate, "iii", $quantity, $productId, $quantity);
                mysqli_stmt_execute($stockUpdate);

                if (mysqli_stmt_affected_rows($stockUpdate) !== 1) {
                    throw new Exception("Stock changed while processing payment. Please review your cart.");
                }
            }

            cartClear($conn, $userId);
            logAudit($conn, $userId, "Create Order", "orders", $orderId, "Created simulated payment order #" . $orderId);

            mysqli_commit($conn);
            unset($_SESSION["pending_checkout"]);

            setFlash("success", "Payment simulated successfully. Order #" . $orderId . " has been created.");
            redirect("index.php");
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $message = $e->getMessage();
        }
    }
}

$pageTitle = "Payment";
require __DIR__ . "/header.php";
?>

<main class="page-main">
    <div class="admin-bar">
        <div>
            <div class="eyebrow">Buyer Checkout</div>
            <h2>Payment</h2>
            <p>This is a simulated payment step. No external payment API is used.</p>
        </div>
    </div>

    <?php if ($message !== ""): ?>
        <div class="error"><?php echo e($message); ?></div>
    <?php endif; ?>

    <div class="detail-grid">
        <div class="card">
            <h3>Review</h3>
            <p><strong>Payment Method:</strong> <?php echo e($pendingCheckout["payment_method"]); ?></p>
            <p><strong>Shipping Address:</strong> <?php echo e($pendingCheckout["shipping_address"]); ?></p>
            <p class="price">Total: <?php echo formatPrice($total); ?></p>
        </div>

        <form method="post" action="payment.php">
            <?php echo csrfField(); ?>
            <h3>Confirm Order</h3>
            <p>Confirming will create an order record and subtract the purchased quantity from stock.</p>
            <div class="form-actions">
                <button type="submit">Confirm Payment</button>
                <a href="checkout.php">Back to Checkout</a>
            </div>
        </form>
    </div>
</main>

<?php require __DIR__ . "/footer.php"; ?>
