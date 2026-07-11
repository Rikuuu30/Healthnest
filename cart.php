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

$pageTitle = "Cart";
require __DIR__ . "/header.php";
?>

<main class="page-main">
    <div class="admin-bar">
        <div>
            <div class="eyebrow">Buyer Cart</div>
            <h2>Shopping Cart</h2>
            <p>Review quantities before checkout. Stock limits are still checked by PHP.</p>
        </div>
    </div>

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
        <form method="post" action="cart.php">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="update">

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
                                <input type="number" name="quantity[<?php echo (int) $item["product_id"]; ?>]" value="<?php echo (int) $item["quantity"]; ?>" min="0" max="<?php echo (int) $item["stock_quantity"]; ?>">
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

            <div class="form-actions">
                <p class="price">Total: <?php echo formatPrice($total); ?></p>
                <button type="submit">Update Cart</button>
                <a href="checkout.php">Proceed to Checkout</a>
            </div>
        </form>
    <?php endif; ?>
</main>

<?php require __DIR__ . "/footer.php"; ?>
