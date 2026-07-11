<?php

require_once __DIR__ . "/init.php";

if (isLoggedIn() && isAdmin()) {
    redirect("seller_dashboard.php");
}

$categoryId = filter_input(INPUT_GET, "category", FILTER_VALIDATE_INT);
$categories = getCategories($conn);
$products = getProducts($conn, $categoryId);
$selectedCategory = $categoryId ? getCategoryById($conn, $categoryId) : null;

$pageTitle = "Products";
require __DIR__ . "/header.php";
?>

<main class="page-main">
    <div class="admin-bar">
        <div>
            <div class="eyebrow">Buyer Catalog</div>
            <h2>Products</h2>
            <p>Browse active HealthNest products and filter by wellness category.</p>
        </div>
    </div>

    <form method="get" action="products.php">
        <label for="category">Category</label>
        <select id="category" name="category">
            <option value="">All Categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo (int) $category["category_id"]; ?>" <?php echo $categoryId === (int) $category["category_id"] ? "selected" : ""; ?>>
                    <?php echo e($category["category_name"]); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Filter</button>
    </form>

    <?php if ($selectedCategory): ?>
        <div class="panel">
            <h3><?php echo e($selectedCategory["category_name"]); ?></h3>
            <p><?php echo e($selectedCategory["description"]); ?></p>
        </div>
    <?php endif; ?>

    <?php if (count($products) > 0): ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <div class="card product-card">
                    <span class="badge"><?php echo e($product["category_name"] ?? "Uncategorized"); ?></span>
                    <h3><?php echo e($product["product_name"]); ?></h3>
                    <p><?php echo e($product["description"] ?: "No description available."); ?></p>
                    <p class="price"><?php echo formatPrice($product["price"]); ?></p>
                    <p class="muted">Stock: <?php echo (int) $product["stock_quantity"]; ?></p>
                    <a href="product.php?id=<?php echo (int) $product["product_id"]; ?>">View Product</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <p>No active products found.</p>
        </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . "/footer.php"; ?>
