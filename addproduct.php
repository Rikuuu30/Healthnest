<?php

require_once __DIR__ . "/init.php";

requireAdmin();

$message = "";
$categories = getCategories($conn);
$values = [
    "product_name" => "",
    "category_id" => "",
    "description" => "",
    "price" => "",
    "stock_quantity" => "",
    "image" => "placeholder.jpg",
    "status" => "active",
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    foreach ($values as $key => $value) {
        $values[$key] = trim($_POST[$key] ?? "");
    }

    $categoryId = (int) $values["category_id"];
    $price = (float) $values["price"];
    $stockQuantity = (int) $values["stock_quantity"];
    $status = strtolower($values["status"]);
    $image = $values["image"] !== "" ? $values["image"] : "placeholder.jpg";

    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        $message = "Your session expired. Please try again.";
    } elseif ($values["product_name"] === "" || $categoryId <= 0 || $values["price"] === "" || $values["stock_quantity"] === "") {
        $message = "Please fill in all required fields.";
    } elseif (!getCategoryById($conn, $categoryId)) {
        $message = "Please select a valid category.";
    } elseif (!is_numeric($values["price"]) || $price <= 0) {
        $message = "Price must be greater than zero.";
    } elseif (!ctype_digit((string) $values["stock_quantity"])) {
        $message = "Stock must be a whole number and cannot be negative.";
    } elseif (!in_array($status, ["active", "inactive"], true)) {
        $message = "Please select a valid status.";
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO products (category_id, product_name, description, price, stock_quantity, image, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())");
        mysqli_stmt_bind_param(
            $stmt,
            "issdiss",
            $categoryId,
            $values["product_name"],
            $values["description"],
            $price,
            $stockQuantity,
            $image,
            $status
        );
        mysqli_stmt_execute($stmt);

        $productId = mysqli_insert_id($conn);
        logAudit($conn, sessionUserId(), "Add Product", "products", $productId, "Added product: " . $values["product_name"]);

        setFlash("success", "Product added successfully.");
        redirect("inventory.php");
    }
}

$pageTitle = "Add Product";
require __DIR__ . "/header.php";
?>

<main class="page-main">
    <div class="admin-bar">
        <div>
            <div class="eyebrow">Seller Tools</div>
            <h2>Add Product</h2>
            <p>Create a new HealthNest product using the existing product and category fields.</p>
        </div>
    </div>

    <?php if ($message !== ""): ?>
        <div class="error"><?php echo e($message); ?></div>
    <?php endif; ?>

    <form method="post" action="addproduct.php">
        <?php echo csrfField(); ?>

        <label for="product_name">Product Name</label><br>
        <input id="product_name" type="text" name="product_name" value="<?php echo e($values["product_name"]); ?>" required><br><br>

        <label for="category_id">Category</label><br>
        <select id="category_id" name="category_id" required>
            <option value="">Select category</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo (int) $category["category_id"]; ?>" <?php echo (int) $values["category_id"] === (int) $category["category_id"] ? "selected" : ""; ?>>
                    <?php echo e($category["category_name"]); ?>
                </option>
            <?php endforeach; ?>
        </select><br><br>

        <label for="description">Description</label><br>
        <textarea id="description" name="description"><?php echo e($values["description"]); ?></textarea><br><br>

        <label for="price">Price</label><br>
        <input id="price" type="number" name="price" min="0.01" step="0.01" value="<?php echo e($values["price"]); ?>" required><br><br>

        <label for="stock_quantity">Stock</label><br>
        <input id="stock_quantity" type="number" name="stock_quantity" min="0" step="1" value="<?php echo e($values["stock_quantity"]); ?>" required><br><br>

        <label for="image">Image Filename</label><br>
        <input id="image" type="text" name="image" value="<?php echo e($values["image"]); ?>"><br><br>

        <label for="status">Status</label><br>
        <select id="status" name="status" required>
            <option value="active" <?php echo $values["status"] === "active" ? "selected" : ""; ?>>Active</option>
            <option value="inactive" <?php echo $values["status"] === "inactive" ? "selected" : ""; ?>>Inactive</option>
        </select><br><br>

        <button type="submit">Save Product</button>
    </form>
</main>

<?php require __DIR__ . "/footer.php"; ?>
