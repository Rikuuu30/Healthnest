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
    <div class="seller-page-header">
        <div>
            <div class="eyebrow">Seller Tools</div>
            <h2>Add Product</h2>
            <p>Create a polished catalog item with complete pricing, stock, visibility, and buyer-facing product details.</p>
        </div>
        <a class="button secondary" href="inventory.php">Back to Inventory</a>
    </div>

    <?php if ($message !== ""): ?>
        <div class="error"><?php echo e($message); ?></div>
    <?php endif; ?>

    <div class="seller-form-layout">
        <form class="seller-form" method="post" action="addproduct.php">
            <?php echo csrfField(); ?>
            <h3>Product Information</h3>

            <div class="form-grid">
                <div class="full">
                    <label for="product_name">Product Name</label>
                    <input id="product_name" type="text" name="product_name" value="<?php echo e($values["product_name"]); ?>" placeholder="Example: CJC-1295" required>
                </div>

                <div>
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">Select category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo (int) $category["category_id"]; ?>" <?php echo (int) $values["category_id"] === (int) $category["category_id"] ? "selected" : ""; ?>>
                                <?php echo e($category["category_name"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        <option value="active" <?php echo $values["status"] === "active" ? "selected" : ""; ?>>Active</option>
                        <option value="inactive" <?php echo $values["status"] === "inactive" ? "selected" : ""; ?>>Inactive</option>
                    </select>
                </div>

                <div>
                    <label for="price">Price</label>
                    <input id="price" type="number" name="price" min="0.01" step="0.01" value="<?php echo e($values["price"]); ?>" placeholder="0.00" required>
                </div>

                <div>
                    <label for="stock_quantity">Stock</label>
                    <input id="stock_quantity" type="number" name="stock_quantity" min="0" step="1" value="<?php echo e($values["stock_quantity"]); ?>" placeholder="0" required>
                </div>

                <div class="full">
                    <label for="image">Image Filename</label>
                    <input id="image" type="text" name="image" value="<?php echo e($values["image"]); ?>" placeholder="placeholder.jpg">
                </div>

                <div class="full">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Write a concise product description for buyers."><?php echo e($values["description"]); ?></textarea>
                </div>
            </div>

            <button type="submit">Save Product</button>
        </form>

        <aside class="seller-assist-panel">
            <div class="card preview-card">
                <span class="panel-label">Catalog Preview</span>
                <h3><?php echo e($values["product_name"] !== "" ? $values["product_name"] : "New Product"); ?></h3>
                <p><?php echo e($values["description"] !== "" ? $values["description"] : "Add a clear benefit-driven description to help buyers understand this item."); ?></p>
                <p class="price"><?php echo is_numeric($values["price"]) && (float) $values["price"] > 0 ? formatPrice((float) $values["price"]) : "Set price"; ?></p>
                <span class="status <?php echo e($values["status"]); ?>"><?php echo e(ucfirst($values["status"])); ?></span>
            </div>

            <div class="card checklist-card">
                <h3>Listing Checklist</h3>
                <ul class="feature-list">
                    <li>Use a short, searchable product name.</li>
                    <li>Choose the most accurate wellness category.</li>
                    <li>Keep stock updated to avoid checkout issues.</li>
                    <li>Set inactive if the product is not ready for buyers.</li>
                </ul>
            </div>
        </aside>
    </div>
</main>

<?php require __DIR__ . "/footer.php"; ?>
