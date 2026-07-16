<?php
require_once __DIR__ . "/init.php";
if (isLoggedIn() && isAdmin()) {
    redirect("seller_dashboard.php");
}
$result = mysqli_query($conn, "
SELECT c.category_id, c.category_name, c.description,
       COUNT(p.product_id) AS product_count,
       COALESCE(MIN(p.price), 0) AS min_price,
       COALESCE(MAX(p.price), 0) AS max_price,
       COALESCE(SUM(p.stock_quantity), 0) AS total_stock
FROM categories c
LEFT JOIN products p ON c.category_id = p.category_id AND p.status = 'active'
GROUP BY c.category_id, c.category_name, c.description
ORDER BY c.category_name
");
$pageTitle = "Categories";
require __DIR__ . "/header.php";
?>
<main class="page-main">
    <section class="seller-page-header buyer-page-header">
        <div>
            <span class="panel-label">Buyer Catalog</span>
            <h2>Categories</h2>
            <p>Choose a HealthNest category to view the active products under it.</p>
        </div>
        <a class="button secondary" href="products.php">All Products</a>
    </section>

    <div class="category-grid buyer-category-grid">
        <?php while ($category = mysqli_fetch_assoc($result)): ?>
            <article class="card category-card buyer-category-card">
                <div class="category-visual" aria-hidden="true">
                    <span><?php echo e(substr((string) $category["category_name"], 0, 1)); ?></span>
                </div>
                <h3>
                    <a href="products.php?category=<?php echo (int) $category["category_id"]; ?>">
                        <?php echo e($category["category_name"]); ?>
                    </a>
                </h3>
                <p><?php echo e($category["description"]); ?></p>
                <div class="buyer-category-metrics">
                    <div>
                        <span>Price Range</span>
                        <strong class="category-price-range">
                            <?php if ((int) $category["product_count"] > 0): ?>
                                <span><?php echo formatPrice($category["min_price"]); ?></span>
                                <span class="range-separator">to</span>
                                <span><?php echo formatPrice($category["max_price"]); ?></span>
                            <?php else: ?>
                                No active pricing
                            <?php endif; ?>
                        </strong>
                    </div>
                    <div>
                        <span>Total Stock</span>
                        <strong><?php echo (int) $category["total_stock"]; ?> units</strong>
                    </div>
                </div>
                <div class="buyer-card-footer">
                    <span class="badge"><?php echo (int) $category["product_count"]; ?> product(s)</span>
                    <a href="products.php?category=<?php echo (int) $category["category_id"]; ?>">Browse</a>
                </div>
            </article>
        <?php endwhile; ?>
    </div>
</main>
<?php require __DIR__ . "/footer.php"; ?>
