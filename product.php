<?php

require_once __DIR__ . "/init.php";

if (isLoggedIn() && isAdmin()) {
    redirect("inventory.php");
}

$productId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$productId) {
    die("Invalid product ID.");
}

$product = getProductById($conn, $productId);

if (!$product || strtolower((string) $product["status"]) !== "active") {
    die("Product not found.");
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isLoggedIn()) {
        setFlash("error", "Please log in before adding products to your cart.");
        redirect("login.php");
    }

    $quantity = filter_input(INPUT_POST, "quantity", FILTER_VALIDATE_INT);

    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        $message = "Your session expired. Please try again.";
    } elseif (!$quantity || $quantity <= 0) {
        $message = "Please enter a valid quantity.";
    } else {
        $stmt = mysqli_prepare($conn, "SELECT quantity FROM cart WHERE user_id = ? AND product_id = ? LIMIT 1");
        $userId = sessionUserId();
        mysqli_stmt_bind_param($stmt, "ii", $userId, $productId);
        mysqli_stmt_execute($stmt);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $existingQty = $existing ? (int) $existing["quantity"] : 0;

        if ($existingQty + $quantity > (int) $product["stock_quantity"]) {
            $message = "That quantity is higher than the available stock.";
        } else {
            cartAdd($conn, $userId, $productId, $quantity);
            setFlash("success", "Product added to cart.");
            redirect("cart.php");
        }
    }
}

$pageTitle = $product["product_name"];
require __DIR__ . "/header.php";
?>

<main class="page-main">
    <div class="admin-menu">
        <a href="products.php">Back to Products</a>
        <a href="categories.php">Browse Categories</a>
    </div>

    <?php if ($message !== ""): ?>
        <div class="error"><?php echo e($message); ?></div>
    <?php endif; ?>

    <div class="detail-grid">
        <div class="card product-card">
            <span class="badge"><?php echo e($product["category_name"] ?? "Uncategorized"); ?></span>
            <h2><?php echo e($product["product_name"]); ?></h2>
            <p class="lead"><?php echo e($product["description"] ?: "No description available."); ?></p>
            <p class="price"><?php echo formatPrice($product["price"]); ?></p>
            <p class="muted">Product ID: <?php echo (int) $product["product_id"]; ?></p>
        </div>

        <div class="panel">
            <h3>Purchase Details</h3>
            <p><strong>Stock:</strong> <?php echo (int) $product["stock_quantity"]; ?></p>

            <?php if ((int) $product["stock_quantity"] > 0): ?>
                <form method="post" action="product.php?id=<?php echo $productId; ?>">
                    <?php echo csrfField(); ?>
                    <label for="quantity">Quantity</label>
                    <input id="quantity" type="number" name="quantity" value="1" min="1" max="<?php echo (int) $product["stock_quantity"]; ?>" required>
                    <button type="submit">Add to Cart</button>
                </form>
            <?php else: ?>
                <div class="empty-state">
                    <p>This product is out of stock.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require __DIR__ . "/footer.php"; ?>
