<?php

require_once __DIR__ . "/init.php";

requireLogin();

$userId = sessionUserId();
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        $message = "Your session expired. Please try again.";
    } else {
        $action = $_POST["action"] ?? "";

        if (isset($_POST["remove_product_id"])) {
            $productId = filter_input(INPUT_POST, "remove_product_id", FILTER_VALIDATE_INT);
            if ($productId) {
                cartRemove($conn, $userId, $productId);
                setFlash("success", "Item removed from cart.");
                redirect("cart.php");
            }
        }

        if ($action === "add") {
            $productId = filter_input(INPUT_POST, "product_id", FILTER_VALIDATE_INT);
            $product = $productId ? getProductById($conn, $productId) : null;

            if (!$product || strtolower((string) $product["status"]) !== "active") {
                setFlash("error", "Product is no longer available.");
            } elseif ((int) $product["stock_quantity"] <= 0) {
                setFlash("error", "Product is out of stock.");
            } else {
                cartAdd($conn, $userId, $productId, 1);
                setFlash("success", $product["product_name"] . " was added to your cart.");
            }

            redirect("cart.php");
        }

        if ($action === "update") {
            foreach (($_POST["quantity"] ?? []) as $productId => $quantity) {
                $productId = (int) $productId;
                $quantity = (int) $quantity;
                $product = getProductById($conn, $productId);

                if (!$product || strtolower((string) $product["status"]) !== "active") {
                    cartRemove($conn, $userId, $productId);
                    continue;
                }

                if ($quantity > (int) $product["stock_quantity"]) {
                    $quantity = (int) $product["stock_quantity"];
                }

                cartUpdate($conn, $userId, $productId, $quantity);
            }

            setFlash("success", "Cart updated.");
            redirect("cart.php");
        }
    }
}

$items = cartItems($conn, $userId);
$total = cartTotal($conn, $userId);
$itemCount = array_sum(array_map(fn($item) => (int) $item["quantity"], $items));
$stockWarnings = count(array_filter($items, fn($item) => (int) $item["quantity"] >= (int) $item["stock_quantity"]));
$cartProductIds = array_map(fn($item) => (int) $item["product_id"], $items);
$recommendedProducts = array_values(array_filter(getProducts($conn), fn($product) => !in_array((int) $product["product_id"], $cartProductIds, true) && (int) $product["stock_quantity"] > 0));
$recommendedProducts = array_slice($recommendedProducts, 0, 3);

$pageTitle = "Cart";
require __DIR__ . "/header.php";
?>

<main class="page-main">
    <section class="seller-page-header buyer-page-header">
        <div>
            <span class="panel-label">Buyer Cart</span>
            <h2>Shopping Cart</h2>
            <p>Review quantities before checkout. Stock limits are still checked by PHP.</p>
        </div>
        <a class="button secondary" href="products.php">Continue Shopping</a>
    </section>

    <section class="dashboard-stat-strip buyer-stat-strip">
        <div>
            <span>Line Items</span>
            <strong><?php echo count($items); ?></strong>
        </div>
        <div>
            <span>Total Units</span>
            <strong><?php echo $itemCount; ?></strong>
        </div>
        <div>
            <span>Stock Alerts</span>
            <strong><?php echo $stockWarnings; ?></strong>
        </div>
        <div>
            <span>Cart Total</span>
            <strong><?php echo formatPrice($total); ?></strong>
        </div>
    </section>

    <?php if ($message !== ""): ?>
        <div class="error"><?php echo e($message); ?></div>
    <?php endif; ?>

    <?php if (count($items) === 0): ?>
        <div class="empty-state">
            <p>Your cart is empty.</p>
            <div class="actions">
                <a href="products.php">Browse Products</a>
            </div>
        </div>
    <?php else: ?>
        <form method="post" action="cart.php" class="buyer-cart-layout">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="update">

            <section class="table-card buyer-cart-table">
                <div class="table-card-header">
                    <div>
                        <span class="panel-label">Cart Items</span>
                        <h3>Review Products</h3>
                    </div>
                </div>
                <div class="table-wrap">
                    <table border="1" cellpadding="8" cellspacing="0">
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                            <th>Action</th>
                        </tr>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><strong><?php echo e($item["product_name"]); ?></strong></td>
                                <td><?php echo e($item["category_name"] ?? "Uncategorized"); ?></td>
                                <td><?php echo formatPrice($item["price"]); ?></td>
                                <td>
                                    <input type="number" name="quantity[<?php echo (int) $item["product_id"]; ?>]" value="<?php echo (int) $item["quantity"]; ?>" min="0" max="<?php echo (int) $item["stock_quantity"]; ?>" data-cart-quantity>
                                    <p class="meta">Available: <?php echo (int) $item["stock_quantity"]; ?></p>
                                </td>
                                <td><?php echo formatPrice($item["subtotal"]); ?></td>
                                <td>
                                    <button type="submit" name="remove_product_id" value="<?php echo (int) $item["product_id"]; ?>">Remove</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </section>

            <aside class="card buyer-cart-summary">
                <span class="panel-label">Checkout Summary</span>
                <h3>Order Snapshot</h3>
                <div class="buyer-summary-total"><?php echo formatPrice($total); ?></div>
                <div class="buyer-cart-summary-stats">
                    <div>
                        <span>Items</span>
                        <strong><?php echo count($items); ?></strong>
                    </div>
                    <div>
                        <span>Units</span>
                        <strong><?php echo $itemCount; ?></strong>
                    </div>
                </div>
                <p class="muted">Use zero quantity to remove an item during update, or remove it immediately from the table.</p>
                <div class="form-actions">
                    <button type="submit">Update Cart</button>
                    <a href="checkout.php">Proceed to Checkout</a>
                </div>
                <div class="buyer-info-note">
                    <strong>Checkout tip</strong>
                    <p>Update quantities first so stock and totals are refreshed before proceeding to checkout.</p>
                </div>
            </aside>
        </form>
        <?php if (count($recommendedProducts) > 0): ?>
            <section class="card buyer-related-panel">
                <div class="card-heading-row compact">
                    <div>
                        <span class="panel-label">Recommended Add-ons</span>
                        <h3>Continue Building Your Cart</h3>
                        <p class="muted">Available products not currently in your cart.</p>
                    </div>
                </div>
                <div class="product-grid buyer-product-grid">
                    <?php foreach ($recommendedProducts as $recommended): ?>
                        <?php $recommendedImage = "images/products/" . productImageFilename($recommended["image"] ?? ""); ?>
                        <article class="card product-card buyer-product-card">
                            <div class="product-image-wrap">
                                <img src="<?php echo e($recommendedImage); ?>" alt="<?php echo e($recommended["product_name"]); ?>" class="product-image" onerror="this.closest('.product-image-wrap').classList.add('image-missing'); this.remove();">
                            </div>
                            <span class="badge"><?php echo e($recommended["category_name"] ?? "Uncategorized"); ?></span>
                            <h3><?php echo e($recommended["product_name"]); ?></h3>
                            <p class="price"><?php echo formatPrice($recommended["price"]); ?></p>
                            <div class="recommended-card-actions">
                                <a href="product.php?id=<?php echo (int) $recommended["product_id"]; ?>">View Product</a>
                                <form method="post" action="cart.php">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="product_id" value="<?php echo (int) $recommended["product_id"]; ?>">
                                    <button type="submit">Add to Cart</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</main>

<script>
(() => {
    document.querySelectorAll("[data-cart-quantity]").forEach((input) => {
        input.addEventListener("change", () => {
            const max = Number(input.max) || 0;
            const value = Math.max(0, Math.min(max, Number(input.value) || 0));
            input.value = String(value);
        });
    });
})();
</script>

<?php require __DIR__ . "/footer.php"; ?>
