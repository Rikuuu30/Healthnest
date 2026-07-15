<?php
require_once __DIR__ . "/init.php";
if (isLoggedIn() && isAdmin()) {
    redirect("seller_dashboard.php");
}
$result = mysqli_query($conn, "
SELECT c.category_id, c.category_name, c.description, COUNT(p.product_id) AS product_count
FROM categories c
LEFT JOIN products p ON c.category_id = p.category_id AND p.status = 'active'
GROUP BY c.category_id, c.category_name, c.description
ORDER BY c.category_name
");
$pageTitle = "Categories";
require __DIR__ . "/header.php";
?>
<main class="page-main">
    <div class="admin-bar">
        <div>
            <div class="eyebrow">Buyer Catalog</div>
            <h2>Categories</h2>
            <p>Choose a HealthNest category to view the active products under it.</p>
        </div>
    </div>
    <div class="category-grid">
        <?php while ($category = mysqli_fetch_assoc($result)): ?>
            <?php 
            // Categories don't have an image column in the DB, so we format the name
            $catImageName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $category["category_name"]) . ".png";
            $catImagePath = "images/categories/" . $catImageName;
            ?>
            <div class="card category-card">
                <div class="category-image-wrap">
                    <img src="<?php echo htmlspecialchars($catImagePath); ?>" 
                         alt="<?php echo e($category["category_name"]); ?>" 
                         class="category-image"
                         onerror="this.src='images/placeholder.png'; this.onerror=null;">
                </div>
                <h3>
                    <a href="products.php?category=<?php echo (int) $category["category_id"]; ?>">
                        <?php echo e($category["category_name"]); ?>
                    </a>
                </h3>
                <p><?php echo e($category["description"]); ?></p>
                <span class="badge"><?php echo (int) $category["product_count"]; ?> product(s)</span>
            </div>
        <?php endwhile; ?>
    </div>
</main>
<?php require __DIR__ . "/footer.php"; ?>
