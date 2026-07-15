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
$itemCount = array_sum(array_map(fn($item) => (int) $item["quantity"], $items));
$highestStockPressure = 0;
foreach ($items as $item) {
    $highestStockPressure = max($highestStockPressure, (int) $item["stock_quantity"] > 0 ? ((int) $item["quantity"] / (int) $item["stock_quantity"]) : 1);
}
$checkoutReadiness = $shippingAddress !== "" ? 100 : 70;

$pageTitle = "Checkout";
require __DIR__ . "/header.php";
?>

<main class="page-main">
    <section class="buyer-page-header">
        <div>
            <span class="buyer-kicker">Buyer Checkout</span>
            <h2>Checkout</h2>
            <p>Confirm delivery, payment preference, and stock-aware order details before payment confirmation.</p>
        </div>
        <a class="button secondary" href="cart.php">Back to Cart</a>
    </section>

    <section class="buyer-value-strip">
        <div><span>Items</span><strong><?php echo count($items); ?></strong></div>
        <div><span>Total Units</span><strong><?php echo $itemCount; ?></strong></div>
        <div><span>Readiness</span><strong><?php echo $checkoutReadiness; ?>%</strong></div>
        <div><span>Total</span><strong><?php echo formatPrice($total); ?></strong></div>
    </section>

    <?php if ($message !== ""): ?>
        <div class="error"><?php echo e($message); ?></div>
    <?php endif; ?>

    <div class="buyer-detail-layout">
        <div class="card buyer-checkout-summary">
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
            <div class="buyer-info-note">
                <strong>Stock check</strong>
                <p><?php echo $highestStockPressure >= 0.8 ? "One or more items are close to available stock limits. Checkout will revalidate before payment." : "All quantities are currently within available stock limits."; ?></p>
            </div>
        </div>

        <form method="post" action="checkout.php" class="buyer-checkout-form">
            <?php echo csrfField(); ?>

            <h3>Delivery and Payment</h3>

            <label for="shipping_address">Shipping Address</label>
            <textarea id="shipping_address" name="shipping_address" required><?php echo e($shippingAddress); ?></textarea>
            <?php if (!empty($user["address"])): ?>
                <button class="button secondary" type="button" id="useProfileAddress">Use Profile Address</button>
            <?php endif; ?>

            <label for="payment_method">Payment Method</label>
            <select id="payment_method" name="payment_method" required>
                <option value="Simulated Card" <?php echo $paymentMethod === "Simulated Card" ? "selected" : ""; ?>>Simulated Card</option>
                <option value="Cash on Delivery" <?php echo $paymentMethod === "Cash on Delivery" ? "selected" : ""; ?>>Cash on Delivery</option>
                <option value="Bank Transfer" <?php echo $paymentMethod === "Bank Transfer" ? "selected" : ""; ?>>Bank Transfer</option>
            </select>
            <div class="buyer-payment-info" id="paymentInfo">Simulated card keeps the demo flow instant and creates an order after confirmation.</div>

            <button type="submit">Continue to Payment</button>
        </form>
    </div>
</main>
<script>
(() => {
    const address = document.getElementById("shipping_address");
    const useProfile = document.getElementById("useProfileAddress");
    const payment = document.getElementById("payment_method");
    const info = document.getElementById("paymentInfo");
    const messages = {
        "Simulated Card": "Simulated card keeps the demo flow instant and creates an order after confirmation.",
        "Cash on Delivery": "Cash on Delivery records the order now and marks the selected payment preference for seller review.",
        "Bank Transfer": "Bank Transfer is recorded for the seller; keep your order number for reference."
    };

    if (useProfile && address) {
        useProfile.addEventListener("click", () => {
            address.value = <?php echo json_encode($user["address"] ?? ""); ?>;
            address.focus();
        });
    }

    if (payment && info) {
        payment.addEventListener("change", () => {
            info.textContent = messages[payment.value] || messages["Simulated Card"];
        });
    }
})();
</script>

<?php require __DIR__ . "/footer.php"; ?>
