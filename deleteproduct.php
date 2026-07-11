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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("error", "Your session expired. Please try again.");
        redirect("deleteproduct.php?id=" . $productId);
    }

    if (($_POST["confirm"] ?? "") === "Yes") {
        $status = "inactive";
        $stmt = mysqli_prepare($conn, "UPDATE products SET status = ? WHERE product_id = ?");
        mysqli_stmt_bind_param($stmt, "si", $status, $productId);
        mysqli_stmt_execute($stmt);

        logAudit($conn, sessionUserId(), "Delete Product", "products", $productId, "Set product inactive: " . $product["product_name"]);
        setFlash("success", "Product removed from the storefront.");
    }

    redirect("inventory.php");
}

$pageTitle = "Delete Product";
require __DIR__ . "/header.php";
?>

<main class="page-main">
    <div class="admin-bar">
        <div>
            <div class="eyebrow">Seller Tools</div>
            <h2>Delete Product</h2>
            <p>This will set the product status to inactive and hide it from the storefront.</p>
        </div>
    </div>

    <div class="card">
        <h3><?php echo e($product["product_name"]); ?></h3>
        <p>Confirm this product should no longer appear for buyers.</p>
    </div>

    <form method="post" action="deleteproduct.php?id=<?php echo $productId; ?>">
        <?php echo csrfField(); ?>
        <button type="submit" name="confirm" value="Yes">Yes, remove it</button>
        <button type="submit" name="confirm" value="No">No, keep it</button>
    </form>
</main>

<?php require __DIR__ . "/footer.php"; ?>
