<?php
require_once __DIR__ . "/init.php";

$pageTitle = $pageTitle ?? "HealthNest";
$user = isLoggedIn() ? currentUser($conn) : null;
$cartCount = $user ? cartCount($conn, (int) $user["id"]) : 0;
$currentPage = basename($_SERVER["PHP_SELF"]);
$isAuthPage = in_array($currentPage, ["login.php", "register.php"], true);
$bodyClass = "page-" . str_replace(".php", "", $currentPage);

if ($isAuthPage) {
    $bodyClass .= " auth-page";
}

if ($user) {
    $bodyClass .= isAdmin() ? " seller-page has-sidebar" : " buyer-page";
}

$useSellerShell = $user && isAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?> | HealthNest</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="<?php echo e($bodyClass); ?>">
    <?php if ($useSellerShell): ?>
        <div class="app-shell">
            <aside class="sidebar">
                <a class="sidebar-brand" href="seller_dashboard.php">
                    <img src="assets/healthnest-logo.png" alt="HealthNest logo">
                </a>

                <div class="sidebar-user">
                    <strong><?php echo e(accountFullName($user)); ?></strong>
                    <span><?php echo e(accountLevelLabel($user["level"])); ?></span>
                </div>

                <nav class="sidebar-nav">
                    <a class="<?php echo $currentPage === "seller_dashboard.php" ? "active" : ""; ?>" href="seller_dashboard.php">Dashboard</a>
                    <a class="<?php echo $currentPage === "addproduct.php" ? "active" : ""; ?>" href="addproduct.php">Add Product</a>
                    <a class="<?php echo $currentPage === "inventory.php" ? "active" : ""; ?>" href="inventory.php">Inventory</a>
                    <a class="<?php echo $currentPage === "manageusers.php" ? "active" : ""; ?>" href="manageusers.php">Manage Users</a>
                    <a class="<?php echo $currentPage === "auditlog.php" ? "active" : ""; ?>" href="auditlog.php">Audit Log</a>
                    <a class="<?php echo $currentPage === "profile.php" ? "active" : ""; ?>" href="profile.php">Seller Profile</a>
                </nav>

                <a class="sidebar-logout" href="logout.php">Logout</a>
            </aside>

            <div class="app-content">
    <?php else: ?>
        <header class="site-header">
            <div class="topbar">
                <a class="brand" href="index.php">
                    <img src="assets/healthnest-logo.png" alt="HealthNest logo">
                </a>

                <nav class="main-nav">
                    <?php if ($user): ?>
                        <a class="<?php echo $currentPage === "buyer_dashboard.php" ? "active" : ""; ?>" href="buyer_dashboard.php">Dashboard</a>
                    <?php else: ?>
                        <a class="<?php echo $currentPage === "index.php" ? "active" : ""; ?>" href="index.php">Home</a>
                    <?php endif; ?>
                    <a class="<?php echo $currentPage === "products.php" || $currentPage === "product.php" ? "active" : ""; ?>" href="products.php">Products</a>
                    <a class="<?php echo $currentPage === "categories.php" ? "active" : ""; ?>" href="categories.php">Categories</a>
                    <a class="<?php echo $currentPage === "about.php" ? "active" : ""; ?>" href="about.php">About</a>
                    <?php if ($user): ?>
                        <a class="cart-pill <?php echo $currentPage === "cart.php" ? "active" : ""; ?>" href="cart.php">Cart (<?php echo $cartCount; ?>)</a>
                        <a class="<?php echo $currentPage === "profile.php" ? "active" : ""; ?>" href="profile.php">Profile</a>
                        <a href="logout.php">Logout</a>
                    <?php else: ?>
                        <a class="<?php echo $currentPage === "login.php" ? "active" : ""; ?>" href="login.php">Login</a>
                        <a class="<?php echo $currentPage === "register.php" ? "active" : ""; ?>" href="register.php">Register</a>
                    <?php endif; ?>
                </nav>
            </div>
        </header>
    <?php endif; ?>

    <?php $flash = getFlash(); ?>
    <?php if ($flash): ?>
        <div class="<?php echo e($flash["type"]); ?>">
            <?php echo e($flash["message"]); ?>
        </div>
    <?php endif; ?>
