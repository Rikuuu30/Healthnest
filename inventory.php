<?php

require_once __DIR__ . "/init.php";

requireAdmin();

$search = trim($_GET["search"] ?? "");
$inventoryStatsResult = mysqli_query($conn, "
    SELECT
        COUNT(*) AS total_items,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_items,
        SUM(CASE WHEN stock_quantity <= 10 THEN 1 ELSE 0 END) AS low_stock_items,
        COALESCE(SUM(price * stock_quantity), 0) AS inventory_value
    FROM products
");
$inventoryStats = mysqli_fetch_assoc($inventoryStatsResult);
$totalItems = (int) ($inventoryStats["total_items"] ?? 0);
$activeItems = (int) ($inventoryStats["active_items"] ?? 0);
$lowStockItems = (int) ($inventoryStats["low_stock_items"] ?? 0);
$inventoryValue = (float) ($inventoryStats["inventory_value"] ?? 0);

if ($search !== "") {
    $like = "%" . $search . "%";
    $stmt = mysqli_prepare($conn, "
        SELECT p.product_id, p.product_name, p.description, p.price, p.stock_quantity, p.status, c.category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        WHERE p.product_name LIKE ? OR c.category_name LIKE ?
        ORDER BY p.product_id DESC
    ");
    mysqli_stmt_bind_param($stmt, "ss", $like, $like);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, "
        SELECT p.product_id, p.product_name, p.description, p.price, p.stock_quantity, p.status, c.category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        ORDER BY p.product_id DESC
    ");
}

$pageTitle = "Inventory";
require __DIR__ . "/header.php";
?>

<main class="page-main">
    <div class="seller-page-header">
        <div>
            <div class="eyebrow">Seller Tools</div>
            <h2>Product Inventory</h2>
            <p>Search, review, and update the products listed in the HealthNest catalog.</p>
        </div>
        <a class="button" href="addproduct.php">Add Product</a>
    </div>

    <div class="analytics-grid">
        <div class="card insight-card">
            <span class="panel-label">Total Items</span>
            <strong><?php echo $totalItems; ?></strong>
            <p>Products currently stored in the catalog.</p>
        </div>
        <div class="card insight-card">
            <span class="panel-label">Active Items</span>
            <strong><?php echo $activeItems; ?></strong>
            <p>Visible and available for buyers.</p>
        </div>
        <div class="card insight-card warning">
            <span class="panel-label">Low Stock</span>
            <strong><?php echo $lowStockItems; ?></strong>
            <p>Products at or below 10 units.</p>
        </div>
        <div class="card insight-card">
            <span class="panel-label">Inventory Value</span>
            <strong><?php echo formatPrice($inventoryValue); ?></strong>
            <p>Estimated value of current stock.</p>
        </div>
    </div>

    <form class="toolbar-form" method="get" action="inventory.php">
        <label for="search">Search Product or Category</label>
        <input id="search" type="text" name="search" placeholder="Search product or category" value="<?php echo e($search); ?>">
        <button type="submit">Search</button>
        <?php if ($search !== ""): ?>
            <a class="button secondary" href="inventory.php">Clear</a>
        <?php endif; ?>
    </form>

    <div class="table-card">
        <h3>Inventory List <?php echo $search !== "" ? "- Results for " . e($search) : ""; ?></h3>
        <div class="table-wrap">
            <table border="1" cellpadding="8" cellspacing="0">
                <tr>
                    <th>ID</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <?php $stockClass = (int) $row["stock_quantity"] <= 10 ? "stock-low" : "stock-ok"; ?>
                        <tr>
                            <td><?php echo (int) $row["product_id"]; ?></td>
                            <td><strong><?php echo e($row["product_name"]); ?></strong></td>
                            <td><?php echo e($row["category_name"] ?? "Uncategorized"); ?></td>
                            <td><?php echo e($row["description"] ?: "No description"); ?></td>
                            <td><?php echo formatPrice($row["price"]); ?></td>
                            <td><span class="stock-badge <?php echo $stockClass; ?>"><?php echo (int) $row["stock_quantity"]; ?></span></td>
                            <td><span class="status <?php echo e(strtolower($row["status"])); ?>"><?php echo e(ucfirst($row["status"])); ?></span></td>
                            <td>
                                <a href="editproduct.php?id=<?php echo (int) $row["product_id"]; ?>">Edit</a>
                                <a href="deleteproduct.php?id=<?php echo (int) $row["product_id"]; ?>">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">No products found.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</main>

<?php require __DIR__ . "/footer.php"; ?>
