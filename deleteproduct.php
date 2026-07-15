<?php

require_once __DIR__ . "/init.php";

requireAdmin();

$productId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$productId) {
    die("Invalid product ID.");
}

$product = getProductById($conn, $productId);

if (!$product) {
    die("Product not found.");
}

$salesStmt = mysqli_prepare($conn, "
    SELECT COALESCE(SUM(quantity), 0) AS units_sold,
           COALESCE(SUM(subtotal), 0) AS sales_value,
           COUNT(DISTINCT order_id) AS order_count
    FROM order_items
    WHERE product_id = ?
");
mysqli_stmt_bind_param($salesStmt, "i", $productId);
mysqli_stmt_execute($salesStmt);
$salesStats = mysqli_fetch_assoc(mysqli_stmt_get_result($salesStmt)) ?: [];

$unitsSold = (int) ($salesStats["units_sold"] ?? 0);
$salesValue = (float) ($salesStats["sales_value"] ?? 0);
$orderCount = (int) ($salesStats["order_count"] ?? 0);
$currentStatus = strtolower(trim((string) ($product["status"] ?? "inactive")));
$isActive = $currentStatus === "active";
$imageFilename = productImageFilename($product["image"] ?? "placeholder.jpg");
$imagePath = "images/products/" . $imageFilename;
$hasImage = $imageFilename !== "placeholder.jpg" && is_file(__DIR__ . "/" . $imagePath);
$inventoryValue = (float) $product["price"] * (int) $product["stock_quantity"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("error", "Your session expired. Please try again.");
        redirect("deleteproduct.php?id=" . $productId);
    }

    $action = $_POST["product_action"] ?? "";
    $acknowledged = ($_POST["confirm_visibility_change"] ?? "") === "1";
    $reason = trim((string) ($_POST["visibility_reason"] ?? ""));

    if (!in_array($action, ["hide", "restore"], true)) {
        setFlash("error", "Choose a valid product action.");
        redirect("deleteproduct.php?id=" . $productId);
    }

    if (!$acknowledged) {
        setFlash("error", "Please confirm that you understand the storefront visibility change.");
        redirect("deleteproduct.php?id=" . $productId);
    }

    $status = $action === "restore" ? "active" : "inactive";
    $stmt = mysqli_prepare($conn, "UPDATE products SET status = ? WHERE product_id = ?");
    mysqli_stmt_bind_param($stmt, "si", $status, $productId);
    mysqli_stmt_execute($stmt);

    $auditAction = $action === "restore" ? "Restore Product" : "Delete Product";
    $details = ($action === "restore" ? "Restored product to active: " : "Set product inactive: ") . $product["product_name"];
    if ($reason !== "") {
        $details .= " | Reason: " . $reason;
    }

    logAudit($conn, sessionUserId(), $auditAction, "products", $productId, $details);
    setFlash("success", $action === "restore" ? "Product restored to the storefront." : "Product hidden from the storefront.");
    redirect("inventory.php");
}

$pageTitle = $isActive ? "Remove Product" : "Restore Product";
require __DIR__ . "/header.php";
?>

<main class="page-main delete-product-page">
    <div class="seller-page-header">
        <div>
            <div class="eyebrow">Seller Tools</div>
            <h2><?php echo $isActive ? "Remove Product" : "Restore Product"; ?></h2>
            <p><?php echo $isActive ? "Hide this product from buyers without deleting its history, sales data, or audit trail." : "This product is inactive. You can restore it to the storefront when it is ready for buyers."; ?></p>
        </div>
        <div class="filter-actions">
            <a class="button secondary" href="editproduct.php?id=<?php echo (int) $productId; ?>">Edit Product</a>
            <a class="button secondary" href="inventory.php">Back to Inventory</a>
        </div>
    </div>

    <div class="delete-product-layout">
        <section class="delete-product-card">
            <div class="delete-product-media <?php echo $hasImage ? "has-image" : ""; ?>">
                <?php if ($hasImage): ?>
                    <img src="<?php echo e($imagePath); ?>" alt="<?php echo e($product["product_name"]); ?>">
                <?php else: ?>
                    <span>No image</span>
                <?php endif; ?>
            </div>

            <div class="delete-product-copy">
                <span class="status <?php echo e($currentStatus); ?>"><?php echo e(ucfirst($currentStatus)); ?></span>
                <h3><?php echo e($product["product_name"]); ?></h3>
                <p><?php echo e($product["description"] ?: "No description has been added for this product."); ?></p>
                <div class="delete-product-facts">
                    <div>
                        <span>Category</span>
                        <strong><?php echo e($product["category_name"] ?? "Uncategorized"); ?></strong>
                    </div>
                    <div>
                        <span>Price</span>
                        <strong><?php echo formatPrice($product["price"]); ?></strong>
                    </div>
                    <div>
                        <span>Stock</span>
                        <strong><?php echo (int) $product["stock_quantity"]; ?> units</strong>
                    </div>
                    <div>
                        <span>Stock Value</span>
                        <strong><?php echo formatPrice($inventoryValue); ?></strong>
                    </div>
                </div>
            </div>
        </section>

        <aside class="delete-product-side">
            <section class="delete-impact-card">
                <span class="panel-label">Visibility Impact</span>
                <div class="delete-impact-grid">
                    <div>
                        <span>Orders</span>
                        <strong><?php echo $orderCount; ?></strong>
                    </div>
                    <div>
                        <span>Units Sold</span>
                        <strong><?php echo $unitsSold; ?></strong>
                    </div>
                    <div>
                        <span>Sales Value</span>
                        <strong><?php echo formatPrice($salesValue); ?></strong>
                    </div>
                </div>
                <p class="muted"><?php echo $isActive ? "Hiding keeps records intact and removes the product from buyer catalog pages." : "Restoring makes the product visible again if it has stock and active status."; ?></p>
            </section>

            <form class="delete-confirm-form" method="post" action="deleteproduct.php?id=<?php echo (int) $productId; ?>">
                <?php echo csrfField(); ?>
                <input type="hidden" name="product_action" value="<?php echo $isActive ? "hide" : "restore"; ?>">

                <label for="visibility_reason">Reason or note</label>
                <textarea id="visibility_reason" name="visibility_reason" rows="4" placeholder="<?php echo $isActive ? "Example: discontinued, duplicate listing, supplier delay" : "Example: stock replenished, listing corrected"; ?>"></textarea>

                <label class="delete-confirm-check">
                    <input id="confirm_visibility_change" type="checkbox" name="confirm_visibility_change" value="1">
                    <span><?php echo $isActive ? "I understand this product will be hidden from buyers." : "I understand this product will be visible to buyers again."; ?></span>
                </label>

                <div class="delete-form-actions">
                    <button id="deleteProductSubmit" class="<?php echo $isActive ? "danger-action" : "restore-action"; ?>" type="submit" disabled>
                        <?php echo $isActive ? "Hide Product" : "Restore Product"; ?>
                    </button>
                    <a href="inventory.php">Cancel</a>
                </div>
            </form>
        </aside>
    </div>
</main>

<script>
const visibilityConfirm = document.getElementById("confirm_visibility_change");
const deleteProductSubmit = document.getElementById("deleteProductSubmit");

if (visibilityConfirm && deleteProductSubmit) {
    visibilityConfirm.addEventListener("change", () => {
        deleteProductSubmit.disabled = !visibilityConfirm.checked;
    });
}
</script>

<?php require __DIR__ . "/footer.php"; ?>
