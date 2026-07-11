<?php
require_once __DIR__ . "/init.php";

if (isLoggedIn()) {
    redirect(dashboardUrl());
}

$pageTitle = "Home";
require __DIR__ . "/header.php";
?>

<main class="page-main">
    <div class="page-hero">
        <div class="hero-copy">
            <div class="eyebrow">HealthNest Wellness Shop</div>
            <h1>Wellness products organized for easy shopping.</h1>
            <p class="lead">Browse recovery, metabolic health, anti-aging, and performance support products. Buyer accounts can add items to cart and complete a simulated checkout.</p>

            <div class="actions">
                <a href="products.php">View All Products</a>
                <a class="secondary" href="categories.php">Browse Categories</a>
            </div>
        </div>

        <div class="hero-panel">
            <h3>Shop by Goal</h3>
            <p>Explore the product categories already stored in your HealthNest database.</p>
            <ul class="quick-list">
                <?php foreach (array_slice(getCategories($conn), 0, 4) as $category): ?>
                    <li>
                        <a href="products.php?category=<?php echo (int) $category["category_id"]; ?>">
                            <?php echo e($category["category_name"]); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="admin-bar">
        <div>
            <div class="eyebrow">Featured</div>
            <h2>Featured Products</h2>
        </div>
    </div>

    <div class="product-grid">
        <?php
        $featured = array_slice(getProducts($conn), 0, 4);
        foreach ($featured as $p):
        ?>
            <div class="card product-card">
                <span class="badge"><?php echo e($p["category_name"] ?? "Uncategorized"); ?></span>
                <h3><?php echo e($p["product_name"]); ?></h3>
                <p class="price"><?php echo formatPrice($p["price"]); ?></p>
                <p class="muted">Stock: <?php echo (int) $p["stock_quantity"]; ?></p>
                <a href="product.php?id=<?php echo (int) $p["product_id"]; ?>">View Product</a>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<?php require __DIR__ . "/footer.php"; ?>
