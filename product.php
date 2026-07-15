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
$recentViewedIds = array_values(array_filter(array_map("intval", $_SESSION["recently_viewed_products"] ?? []), function ($id) use ($productId) {
    return $id > 0 && $id !== (int) $productId;
}));
$_SESSION["recently_viewed_products"] = array_slice(array_merge([(int) $productId], $recentViewedIds), 0, 6);
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

// Get image directly from the database
$dbImage = !empty($product["image"]) ? $product["image"] : "placeholder.jpg";
$imagePath = "images/products/" . $dbImage;
$relatedProducts = [];
if (!empty($product["category_id"])) {
    $relatedProducts = array_values(array_filter(getProducts($conn, (int) $product["category_id"]), function ($related) use ($productId) {
        return (int) $related["product_id"] !== (int) $productId;
    }));
    $relatedProducts = array_slice($relatedProducts, 0, 3);
}
$stockQuantity = (int) $product["stock_quantity"];
$stockLabel = $stockQuantity <= 0 ? "Out of stock" : ($stockQuantity <= 10 ? "Low stock" : "Available");
$stockClass = $stockQuantity <= 0 ? "stock-low" : ($stockQuantity <= 10 ? "stock-low" : "stock-ok");
$recentProducts = [];
foreach (array_slice($recentViewedIds, 0, 3) as $recentId) {
    $recentProduct = getProductById($conn, $recentId);
    if ($recentProduct && strtolower((string) $recentProduct["status"]) === "active") {
        $recentProducts[] = $recentProduct;
    }
}
?>
<main class="page-main">
    <section class="seller-page-header buyer-page-header">
        <div>
            <span class="panel-label">Product Detail</span>
            <h2><?php echo e($product["product_name"]); ?></h2>
            <p>Review price, stock, and category details before adding this item to your cart.</p>
        </div>
        <div class="admin-menu">
            <a href="products.php">Back to Products</a>
            <a href="categories.php">Browse Categories</a>
        </div>
    </section>

    <?php if ($message !== ""): ?>
        <div class="error"><?php echo e($message); ?></div>
    <?php endif; ?>

    <div class="buyer-detail-layout">
        <section class="card product-card buyer-product-detail-card">
            <div class="product-detail-image-wrap">
                <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                     alt="<?php echo e($product["product_name"]); ?>" 
                     class="product-detail-image"
                     onerror="this.src='images/placeholder.png'; this.onerror=null;">
            </div>
            <span class="badge"><?php echo e($product["category_name"] ?? "Uncategorized"); ?></span>
            <h2><?php echo e($product["product_name"]); ?></h2>
            <p class="lead"><?php echo e($product["description"] ?: "No description available."); ?></p>
            <div class="buyer-product-facts">
                <div>
                    <span>Price</span>
                    <strong><?php echo formatPrice($product["price"]); ?></strong>
                </div>
                <div>
                    <span>Stock</span>
                    <strong><?php echo $stockQuantity; ?></strong>
                </div>
                <div>
                    <span>Product ID</span>
                    <strong>#<?php echo (int) $product["product_id"]; ?></strong>
                </div>
                <div>
                    <span>Category</span>
                    <strong><?php echo e($product["category_name"] ?? "Uncategorized"); ?></strong>
                </div>
            </div>
            <div class="buyer-info-note">
                <strong>Buyer note</strong>
                <p>Review product details with a licensed professional before use. HealthNest keeps checkout stock-aware so unavailable items cannot proceed.</p>
            </div>
        </section>

        <aside class="buyer-purchase-stack">
            <section class="panel buyer-purchase-card">
                <span class="panel-label">Purchase Details</span>
                <h3>Add to Cart</h3>
                <p><span class="stock-badge <?php echo e($stockClass); ?>"><?php echo e($stockLabel); ?></span></p>
                <?php if ($stockQuantity > 0): ?>
                    <form method="post" action="product.php?id=<?php echo $productId; ?>" id="productPurchaseForm">
                    <?php echo csrfField(); ?>
                    <label for="quantity">Quantity</label>
                    <div class="quantity-stepper">
                        <button type="button" data-step="-1" aria-label="Decrease quantity">-</button>
                        <input id="quantity" type="number" name="quantity" value="1" min="1" max="<?php echo $stockQuantity; ?>" required>
                        <button type="button" data-step="1" aria-label="Increase quantity">+</button>
                    </div>
                    <p class="meta">Available stock: <?php echo $stockQuantity; ?></p>
                    <p class="buyer-estimate">Estimated line total: <strong id="estimatedLineTotal"><?php echo formatPrice($product["price"]); ?></strong></p>
                    <button type="submit">Add to Cart</button>
                    </form>
                <?php else: ?>
                    <div class="empty-state">
                        <p>This product is out of stock.</p>
                    </div>
                <?php endif; ?>
            </section>

            <section class="card buyer-assist-card">
                <span class="panel-label">Shopping Tools</span>
                <h3>Next Steps</h3>
                <div class="buyer-chip-row stacked">
                    <a href="cart.php">Review Cart</a>
                    <a href="products.php?category=<?php echo (int) $product["category_id"]; ?>">More in Category</a>
                    <a href="products.php?q=<?php echo urlencode($product["product_name"]); ?>">Search Similar</a>
                    <a href="buyer_orders.php">My Orders</a>
                </div>
            </section>
        </aside>
    </div>

    <?php if (count($relatedProducts) > 0): ?>
        <section class="card buyer-related-panel">
            <div class="card-heading-row compact">
                <div>
                    <span class="panel-label">Related</span>
                    <h3>Similar Products</h3>
                </div>
            </div>
            <div class="product-grid buyer-product-grid">
                <?php foreach ($relatedProducts as $related): ?>
                    <?php $relatedImagePath = "images/products/" . productImageFilename($related["image"] ?? ""); ?>
                    <article class="card product-card buyer-product-card">
                        <div class="product-image-wrap">
                            <img src="<?php echo e($relatedImagePath); ?>" alt="<?php echo e($related["product_name"]); ?>" class="product-image" onerror="this.src='images/placeholder.png'; this.onerror=null;">
                        </div>
                        <span class="badge"><?php echo e($related["category_name"] ?? "Uncategorized"); ?></span>
                        <h3><?php echo e($related["product_name"]); ?></h3>
                        <p class="price"><?php echo formatPrice($related["price"]); ?></p>
                        <a href="product.php?id=<?php echo (int) $related["product_id"]; ?>">View Product</a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
    <?php if (count($recentProducts) > 0): ?>
        <section class="card buyer-related-panel">
            <div class="card-heading-row compact">
                <div>
                    <span class="panel-label">Recently Viewed</span>
                    <h3>Continue Comparing</h3>
                </div>
            </div>
            <div class="product-grid buyer-product-grid">
                <?php foreach ($recentProducts as $recentProduct): ?>
                    <article class="card product-card buyer-product-card">
                        <span class="badge"><?php echo e($recentProduct["category_name"] ?? "Uncategorized"); ?></span>
                        <h3><?php echo e($recentProduct["product_name"]); ?></h3>
                        <p class="price"><?php echo formatPrice($recentProduct["price"]); ?></p>
                        <a href="product.php?id=<?php echo (int) $recentProduct["product_id"]; ?>">View Again</a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</main>
<script>
(() => {
    const form = document.getElementById("productPurchaseForm");
    if (!form) {
        return;
    }

    const input = form.querySelector("#quantity");
    const estimate = document.getElementById("estimatedLineTotal");
    const unitPrice = <?php echo json_encode((float) $product["price"]); ?>;
    const formatter = new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function updateEstimate() {
        if (!estimate) {
            return;
        }
        estimate.textContent = `PHP ${formatter.format((Number(input.value) || 1) * unitPrice)}`;
    }

    form.querySelectorAll("[data-step]").forEach((button) => {
        button.addEventListener("click", () => {
            const step = Number(button.dataset.step);
            const min = Number(input.min) || 1;
            const max = Number(input.max) || min;
            const next = Math.min(max, Math.max(min, (Number(input.value) || min) + step));
            input.value = String(next);
            updateEstimate();
        });
    });
    input.addEventListener("input", updateEstimate);
    input.addEventListener("change", updateEstimate);
})();
</script>
<?php require __DIR__ . "/footer.php"; ?>
