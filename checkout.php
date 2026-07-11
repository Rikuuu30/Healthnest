<?php

require_once __DIR__ . "/init.php";

requireLogin();

$userId = sessionUserId();
$user = currentUser($conn);
$items = cartItems($conn, $userId);
$message = "";
$shippingAddress = $user["address"] ?? "";
$paymentMethod = "Simulated Card";

if (count($items) === 0) {
    setFlash("error", "Your cart is empty.");
    redirect("cart.php");
}

foreach ($items as $item) {
    if (strtolower((string) $item["status"]) !== "active" || (int) $item["quantity"] > (int) $item["stock_quantity"]) {
        setFlash("error", "Please update your cart before checkout. One or more items are unavailable or above stock.");
        redirect("cart.php");
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $shippingAddress = trim($_POST["shipping_address"] ?? "");
    $paymentMethod = trim($_POST["payment_method"] ?? "");

    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        $message = "Your session expired. Please try again.";
    } elseif ($shippingAddress === "") {
        $message = "Please enter a shipping address.";
    } elseif (!in_array($paymentMethod, ["Simulated Card", "Cash on Delivery", "Bank Transfer"], true)) {
        $message = "Please select a payment method.";
    } else {
        $_SESSION["pending_checkout"] = [
            "shipping_address" => $shippingAddress,
            "payment_method" => $paymentMethod,
        ];
        redirect("payment.php");
    }
}

$total = cartTotal($conn, $userId);

$pageTitle = "Checkout";
require __DIR__ . "/header.php";
?>

<main class="page-main">
    <div class="admin-bar">
        <div>
            <div class="eyebrow">Buyer Checkout</div>
            <h2>Checkout</h2>
            <p>Confirm your order details before moving to the simulated payment step.</p>
        </div>
    </div>

    <?php if ($message !== ""): ?>
        <div class="error"><?php echo e($message); ?></div>
    <?php endif; ?>

    <div class="detail-grid">
        <div class="card">
            <h3>Order Summary</h3>
            <ul class="plain-list">
                <?php foreach ($items as $item): ?>
                    <li>
                        <strong><?php echo e($item["product_name"]); ?></strong>
                        <p class="meta">Qty: <?php echo (int) $item["quantity"]; ?> - <?php echo formatPrice($item["subtotal"]); ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="price">Total: <?php echo formatPrice($total); ?></p>
        </div>

        <form method="post" action="checkout.php">
            <?php echo csrfField(); ?>

            <h3>Delivery and Payment</h3>

            <label for="shipping_address">Shipping Address</label>
            <textarea id="shipping_address" name="shipping_address" required><?php echo e($shippingAddress); ?></textarea>

            <label for="payment_method">Payment Method</label>
            <select id="payment_method" name="payment_method" required>
                <option value="Simulated Card" <?php echo $paymentMethod === "Simulated Card" ? "selected" : ""; ?>>Simulated Card</option>
                <option value="Cash on Delivery" <?php echo $paymentMethod === "Cash on Delivery" ? "selected" : ""; ?>>Cash on Delivery</option>
                <option value="Bank Transfer" <?php echo $paymentMethod === "Bank Transfer" ? "selected" : ""; ?>>Bank Transfer</option>
            </select>

            <button type="submit">Continue to Payment</button>
        </form>
    </div>
</main>

<?php require __DIR__ . "/footer.php"; ?>
