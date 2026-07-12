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

$sidebarInitials = "S";
if ($useSellerShell) {
    $nameParts = preg_split('/\s+/', trim(accountFullName($user)));
    $nameParts = array_values(array_filter($nameParts));
    if (!empty($nameParts)) {
        $sidebarInitials = mb_strtoupper(mb_substr($nameParts[0], 0, 1));
        if (count($nameParts) > 1) {
            $sidebarInitials .= mb_strtoupper(mb_substr($nameParts[count($nameParts) - 1], 0, 1));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?> | HealthNest</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="<?php echo e($bodyClass); ?>">
    <?php if ($useSellerShell): ?>
        <div class="app-shell">
            <aside class="sidebar">
                <div class="sidebar-top">
                    <a class="sidebar-brand" href="seller_dashboard.php">
                        <img src="assets/healthnest_logo.png" alt="HealthNest logo">
                    </a>
                    <span class="sidebar-kicker">Admin Panel</span>
                </div>

                <div class="sidebar-user">
                    <span class="sidebar-avatar"><?php echo e($sidebarInitials); ?></span>
                    <div class="sidebar-user-meta">
                        <strong><?php echo e(accountFullName($user)); ?></strong>
                        <span><?php echo e(accountLevelLabel($user["level"])); ?></span>
                    </div>
                </div>

                <nav class="sidebar-nav">
                    <span class="sidebar-section-label">Workspace</span>
                    <a class="<?php echo $currentPage === "seller_dashboard.php" ? "active" : ""; ?>" href="seller_dashboard.php">
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"></rect><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"></rect><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"></rect><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"></rect></svg></span>
                        <span class="nav-label">Dashboard</span>
                    </a>
                    <a class="<?php echo $currentPage === "addproduct.php" ? "active" : ""; ?>" href="addproduct.php">
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"></circle><path d="M12 8v8M8 12h8"></path></svg></span>
                        <span class="nav-label">Add Product</span>
                    </a>
                    <a class="<?php echo $currentPage === "inventory.php" ? "active" : ""; ?>" href="inventory.php">
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 8 12 4l8.5 4-8.5 4-8.5-4Z"></path><path d="M3.5 8v8L12 20l8.5-4V8"></path><path d="M12 12v8"></path></svg></span>
                        <span class="nav-label">Inventory</span>
                    </a>

                    <span class="sidebar-section-label">Management</span>
                    <a class="<?php echo $currentPage === "manageusers.php" ? "active" : ""; ?>" href="manageusers.php">
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8.5" r="3.25"></circle><path d="M3.5 19c0-3.2 2.6-5.5 5.5-5.5s5.5 2.3 5.5 5.5"></path><path d="M15.5 6.2c1.4.3 2.5 1.6 2.5 3.1s-1.1 2.8-2.5 3.1"></path><path d="M17 13.6c2 .5 3.5 2.4 3.5 4.6"></path></svg></span>
                        <span class="nav-label">Manage Users</span>
                    </a>
                    <a class="<?php echo $currentPage === "auditlog.php" ? "active" : ""; ?>" href="auditlog.php">
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3.5" width="14" height="17" rx="2"></rect><path d="M9 3.5v2.5h6V3.5"></path><path d="M8.5 11.5h7M8.5 15h7M8.5 8.5h3.5"></path></svg></span>
                        <span class="nav-label">Audit Log</span>
                    </a>
                    <a class="<?php echo $currentPage === "profile.php" ? "active" : ""; ?>" href="profile.php">
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"></circle><circle cx="12" cy="9.8" r="2.6"></circle><path d="M6.8 18.2c.9-2.1 2.9-3.4 5.2-3.4s4.3 1.3 5.2 3.4"></path></svg></span>
                        <span class="nav-label">Seller Profile</span>
                    </a>
                </nav>

                <div class="sidebar-footer">
                    <div class="sidebar-status-card">
                        <span>System Mode</span>
                        <strong>Seller Console</strong>
                        <p>Inventory, users, orders, and audit tools are ready.</p>
                    </div>

                    <a class="sidebar-logout" href="logout.php">
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 20H5.5A1.5 1.5 0 0 1 4 18.5v-13A1.5 1.5 0 0 1 5.5 4H9"></path><path d="M16 16l4-4-4-4"></path><path d="M20 12H9"></path></svg></span>
                        <span class="nav-label">Logout</span>
                    </a>
                </div>
            </aside>

            <div class="app-content">
    <?php else: ?>
        <header class="site-header">
            <div class="topbar">
                <a class="brand" href="index.php">
                    <img src="assets/healthnest-logo.png" alt="HealthNest logo">
                </a>
                <div class="topbar-note">Premium Wellness Portal</div>

                <nav class="main-nav">
                    <?php if ($user): ?>
                        <a class="<?php echo $currentPage === "buyer_dashboard.php" ? "active" : ""; ?>" href="buyer_dashboard.php">
                            <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"></rect><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"></rect><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"></rect><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"></rect></svg></span>
                            <span class="nav-label">Dashboard</span>
                        </a>
                    <?php else: ?>
                        <a class="<?php echo $currentPage === "index.php" ? "active" : ""; ?>" href="index.php">
                            <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 11.5 12 4l8 7.5"></path><path d="M6 10v9.5h12V10"></path><path d="M10 19.5V14h4v5.5"></path></svg></span>
                            <span class="nav-label">Home</span>
                        </a>
                    <?php endif; ?>
                    <a class="<?php echo $currentPage === "products.php" || $currentPage === "product.php" ? "active" : ""; ?>" href="products.php">
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6.5 8h11l1 12h-13l1-12Z"></path><path d="M9 8V6.5a3 3 0 0 1 6 0V8"></path></svg></span>
                        <span class="nav-label">Products</span>
                    </a>
                    <a class="<?php echo $currentPage === "categories.php" ? "active" : ""; ?>" href="categories.php">
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11.4 4.5h-4a2 2 0 0 0-1.4.6L4.5 6.6a2 2 0 0 0-.6 1.4v4l9 9a1.6 1.6 0 0 0 2.3 0l5.6-5.6a1.6 1.6 0 0 0 0-2.3l-9-9Z"></path><circle cx="8.5" cy="9" r="1.2"></circle></svg></span>
                        <span class="nav-label">Categories</span>
                    </a>
                    <a class="<?php echo $currentPage === "about.php" ? "active" : ""; ?>" href="about.php">
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"></circle><path d="M12 11v5.5"></path><circle cx="12" cy="8.2" r="0.9" fill="currentColor" stroke="none"></circle></svg></span>
                        <span class="nav-label">About</span>
                    </a>
                    <?php if ($user): ?>
                        <a class="cart-pill <?php echo $currentPage === "cart.php" ? "active" : ""; ?>" href="cart.php">
                            <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h2l1.8 10.6a2 2 0 0 0 2 1.7h7a2 2 0 0 0 2-1.6L20 9H6.3"></path><circle cx="9.5" cy="20" r="1.2"></circle><circle cx="17" cy="20" r="1.2"></circle></svg></span>
                            <span class="nav-label">Cart (<?php echo $cartCount; ?>)</span>
                        </a>
                        <a class="<?php echo $currentPage === "profile.php" ? "active" : ""; ?>" href="profile.php">
                            <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"></circle><circle cx="12" cy="9.8" r="2.6"></circle><path d="M6.8 18.2c.9-2.1 2.9-3.4 5.2-3.4s4.3 1.3 5.2 3.4"></path></svg></span>
                            <span class="nav-label">Profile</span>
                        </a>
                        <a href="logout.php">
                            <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 20H5.5A1.5 1.5 0 0 1 4 18.5v-13A1.5 1.5 0 0 1 5.5 4H9"></path><path d="M16 16l4-4-4-4"></path><path d="M20 12H9"></path></svg></span>
                            <span class="nav-label">Logout</span>
                        </a>
                    <?php else: ?>
                        <a class="<?php echo $currentPage === "login.php" ? "active" : ""; ?>" href="login.php">
                            <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 4h3.5A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5H15"></path><path d="M10 8l4 4-4 4"></path><path d="M14 12H4"></path></svg></span>
                            <span class="nav-label">Login</span>
                        </a>
                        <a class="<?php echo $currentPage === "register.php" ? "active" : ""; ?>" href="register.php">
                            <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9.5" cy="8.5" r="3.25"></circle><path d="M4 19c0-3 2.5-5.3 5.5-5.3s5.5 2.3 5.5 5.3"></path><path d="M18 8v5M15.5 10.5h5"></path></svg></span>
                            <span class="nav-label">Register</span>
                        </a>
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
