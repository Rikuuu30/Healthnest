<?php

require_once __DIR__ . "/init.php";

requireLogin();

if (isAdmin()) {
    redirect("seller_dashboard.php");
}

$userId = sessionUserId();
$user = currentUser($conn);
$cartCount = cartCount($conn, $userId);
$cartTotal = cartTotal($conn, $userId);
$featuredProducts = array_slice(getProducts($conn), 0, 4);

$ordersResult = mysqli_query($conn, "
    SELECT order_id, total_amount, payment_method, status, created_at
    FROM orders
    WHERE user_id = " . (int) $userId . "
    ORDER BY order_id DESC
    LIMIT 5
");

$pageTitle = "Buyer Dashboard";
require __DIR__ . "/header.php";
?>

<main class="page-main">
    <div class="page-hero">
        <div class="hero-copy">
            <div class="eyebrow">Buyer Dashboard</div>
            <h1>Welcome, <?php echo e(accountFullName($user)); ?>.</h1>
            <p class="lead">Continue shopping, review your cart, and check your recent HealthNest orders.</p>

            <div class="actions">
                <a href="products.php">Shop Products</a>
                <a class="secondary" href="cart.php">View Cart</a>
            </div>
        </div>

        <div class="hero-panel">
            <h3>Cart Summary</h3>
            <p><?php echo (int) $cartCount; ?> item(s) currently in your cart.</p>
            <p class="price">Total: <?php echo formatPrice($cartTotal); ?></p>
            <div class="actions">
                <a href="checkout.php">Checkout</a>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div>
            <div class="admin-bar">
                <div>
                    <div class="eyebrow">Recommended</div>
                    <h2>Featured Products</h2>
                </div>
            </div>

            <div class="product-grid">
                <?php foreach ($featuredProducts as $product): ?>
                    <div class="card product-card">
                        <span class="badge"><?php echo e($product["category_name"] ?? "Uncategorized"); ?></span>
                        <h3><?php echo e($product["product_name"]); ?></h3>
                        <p><?php echo e($product["description"] ?: "No description available."); ?></p>
                        <p class="price"><?php echo formatPrice($product["price"]); ?></p>
                        <a href="product.php?id=<?php echo (int) $product["product_id"]; ?>">View Product</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <h3>Recent Orders</h3>
            <?php if ($ordersResult && mysqli_num_rows($ordersResult) > 0): ?>
                <ul class="activity-list">
                    <?php while ($order = mysqli_fetch_assoc($ordersResult)): ?>
                        <li>
                            <strong>Order #<?php echo (int) $order["order_id"]; ?></strong>
                            <p class="muted"><?php echo formatPrice($order["total_amount"]); ?> - <?php echo e(orderStatusLabel($order["status"])); ?></p>
                            <p class="meta"><?php echo e($order["payment_method"]); ?> - <?php echo e($order["created_at"]); ?></p>
                            <p class="meta"><a href="buyer_orders.php?id=<?php echo (int) $order["order_id"]; ?>">Track order</a></p>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <p class="muted">You do not have orders yet.</p>
            <?php endif; ?>

            <div class="actions">
                <a class="secondary" href="buyer_orders.php">Track Orders</a>
                <a class="secondary" href="profile.php">Manage Profile</a>
            </div>
        </div>
    </div>
</main>

<?php require __DIR__ . "/footer.php"; ?>
