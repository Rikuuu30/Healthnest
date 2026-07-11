<?php

require_once __DIR__ . "/init.php";

requireAdmin();

$search = trim($_GET["search"] ?? "");

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
    <div class="admin-bar">
        <div>
            <div class="eyebrow">Seller Tools</div>
            <h2>Product Inventory</h2>
            <p>Search, review, and update the products listed in the HealthNest catalog.</p>
        </div>
    </div>

    <form method="get" action="inventory.php">
        <label for="search">Search Product or Category</label>
        <input id="search" type="text" name="search" placeholder="Search product or category" value="<?php echo e($search); ?>">
        <button type="submit">Search</button>
    </form>

    <div class="table-card">
        <h3>Inventory List</h3>
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
