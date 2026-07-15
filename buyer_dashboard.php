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
$categories = getCategories($conn);

$orderStatsResult = mysqli_query($conn, "
    SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(total_amount), 0) AS total_spent,
        SUM(CASE WHEN LOWER(COALESCE(status, 'paid')) NOT IN ('delivered', 'cancelled') THEN 1 ELSE 0 END) AS active_orders
    FROM orders
    WHERE user_id = " . (int) $userId . "
");
$orderStats = $orderStatsResult ? mysqli_fetch_assoc($orderStatsResult) : [];
$totalOrders = (int) ($orderStats["total_orders"] ?? 0);
$totalSpent = (float) ($orderStats["total_spent"] ?? 0);
$activeOrders = (int) ($orderStats["active_orders"] ?? 0);
$profileFields = ["firstname", "lastname", "email", "contact", "address", "birthdate"];
$completedProfileFields = 0;
foreach ($profileFields as $field) {
    if (trim((string) ($user[$field] ?? "")) !== "") {
        $completedProfileFields++;
    }
}
$profileScore = count($profileFields) > 0 ? round(($completedProfileFields / count($profileFields)) * 100) : 100;
$dashboardPrompt = $cartCount > 0
    ? "You have " . (int) $cartCount . " item(s) waiting for checkout."
    : ($activeOrders > 0 ? "Track " . $activeOrders . " active order(s)." : "Browse the catalog to start a new order.");

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
    <section class="buyer-storefront-hero">
        <div class="buyer-hero-copy">
            <span class="buyer-kicker">Private Wellness Catalog</span>
            <h1>Welcome back, <?php echo e(accountFullName($user)); ?>.</h1>
            <p class="lead">Explore research-focused wellness products, compare categories, and move from discovery to checkout with a cleaner premium buying flow.</p>
            <div class="buyer-hero-actions">
                <a class="button" href="products.php">Shop Catalog</a>
                <a class="button secondary" href="categories.php">Explore Goals</a>
            </div>
        </div>

        <div class="buyer-cart-preview">
            <span>In Your Cart</span>
            <strong><?php echo formatPrice($cartTotal); ?></strong>
            <p><?php echo (int) $cartCount; ?> item(s) ready for review.</p>
            <div class="buyer-mini-actions">
                <a href="cart.php">View Cart</a>
                <?php if ($cartCount > 0): ?>
                    <a href="checkout.php">Checkout</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="buyer-value-strip">
        <div>
            <span>Cart Value</span>
            <strong><?php echo formatPrice($cartTotal); ?></strong>
        </div>
        <div>
            <span>Cart Items</span>
            <strong><?php echo (int) $cartCount; ?></strong>
        </div>
        <div>
            <span>Orders</span>
            <strong><?php echo $totalOrders; ?></strong>
        </div>
        <div>
            <span>Total Spent</span>
            <strong><?php echo formatPrice($totalSpent); ?></strong>
        </div>
    </section>

    <div class="buyer-home-grid">
        <section class="buyer-catalog-panel">
            <div class="buyer-section-heading">
                <div>
                    <span class="buyer-kicker">Recommended</span>
                    <h2>Featured Products</h2>
                    <p>Curated picks from the active catalog, designed for fast comparison.</p>
                </div>
                <a class="button secondary" href="products.php">View All</a>
            </div>

            <div class="product-grid buyer-product-grid">
                <?php foreach ($featuredProducts as $product): ?>
                    <?php $imagePath = "images/products/" . productImageFilename($product["image"] ?? ""); ?>
                    <article class="buyer-product-card">
                        <div class="product-image-wrap">
                            <img src="<?php echo e($imagePath); ?>" alt="<?php echo e($product["product_name"]); ?>" class="product-image" onerror="this.src='images/placeholder.png'; this.onerror=null;">
                        </div>
                        <span class="badge"><?php echo e($product["category_name"] ?? "Uncategorized"); ?></span>
                        <h3><?php echo e($product["product_name"]); ?></h3>
                        <p><?php echo e($product["description"] ?: "No description available."); ?></p>
                        <div class="buyer-card-footer">
                            <p class="price"><?php echo formatPrice($product["price"]); ?></p>
                            <a href="product.php?id=<?php echo (int) $product["product_id"]; ?>">View</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <aside class="buyer-home-aside">
            <section class="buyer-panel">
                <span class="buyer-kicker">Shop by Goal</span>
                <h3>Wellness Categories</h3>
                <div class="buyer-chip-row stacked">
                    <?php foreach ($categories as $category): ?>
                        <a href="products.php?category=<?php echo (int) $category["category_id"]; ?>">
                            <?php echo e($category["category_name"]); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="buyer-panel">
                <div class="buyer-section-heading compact">
                    <div>
                        <span class="buyer-kicker">Account</span>
                        <h3>Recent Orders</h3>
                    </div>
                </div>
                <?php if ($ordersResult && mysqli_num_rows($ordersResult) > 0): ?>
                    <ul class="activity-list buyer-activity-list">
                        <?php while ($order = mysqli_fetch_assoc($ordersResult)): ?>
                            <li>
                                <strong>Order #HN-<?php echo (int) $order["order_id"]; ?></strong>
                                <p class="muted"><?php echo formatPrice($order["total_amount"]); ?> - <?php echo e(orderStatusLabel($order["status"])); ?></p>
                                <p class="meta"><?php echo e($order["payment_method"]); ?> - <?php echo e($order["created_at"]); ?></p>
                                <a href="buyer_orders.php?id=<?php echo (int) $order["order_id"]; ?>">Track order</a>
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
            </section>
        </aside>
    </div>
</main>

<?php require __DIR__ . "/footer.php"; ?>
