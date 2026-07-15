<?php
require_once __DIR__ . "/init.php";

$pageTitle = "About";
require __DIR__ . "/header.php";
?>

<main class="page-main">
    <section class="buyer-storefront-hero buyer-about-hero">
        <div class="buyer-hero-copy">
            <span class="buyer-kicker">About HealthNest</span>
            <h1>Simple PHP shopping for wellness products.</h1>
            <p class="lead">HealthNest is a class project e-commerce application for buyers and sellers. It includes catalog browsing, categories, cart management, checkout, simulated payment, account management, inventory, user management, and audit logs.</p>
            <div class="buyer-hero-actions">
                <a class="button" href="products.php">Browse Products</a>
                <a class="button secondary" href="categories.php">View Categories</a>
            </div>
        </div>

        <div class="buyer-cart-preview">
            <span>Project Scope</span>
            <h3>Built With Covered Modules</h3>
            <ul class="quick-list">
                <li>PHP pages and includes</li>
                <li>Forms and validation</li>
                <li>Sessions and account levels</li>
                <li>MySQL database records</li>
            </ul>
        </div>
    </section>

    <section class="buyer-home-grid">
        <div class="card buyer-about-card">
            <span class="buyer-kicker">Buyer Experience</span>
            <h3>What Buyers Can Do</h3>
            <div class="buyer-feature-grid">
                <div><strong>Browse</strong><span>Search products and filter by category or stock.</span></div>
                <div><strong>Cart</strong><span>Update quantities, remove items, and prepare checkout.</span></div>
                <div><strong>Orders</strong><span>Track seller status updates and order history.</span></div>
                <div><strong>Profile</strong><span>Maintain delivery, contact, and account details.</span></div>
            </div>
        </div>

        <aside class="card buyer-about-card">
            <span class="buyer-kicker">Storefront Design</span>
            <h3>Built for Confident Shopping</h3>
            <p>The buyer side now uses a top navigation, premium product cards, fast category paths, cart momentum, and focused account tools that support product discovery and repeat purchasing.</p>
            <div class="buyer-chip-row stacked">
                <a href="buyer_dashboard.php">Dashboard</a>
                <a href="cart.php">Cart</a>
                <a href="buyer_orders.php">My Orders</a>
            </div>
        </aside>
    </section>
</main>

<?php require __DIR__ . "/footer.php"; ?>
