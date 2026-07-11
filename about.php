<?php
require_once __DIR__ . "/init.php";

if (isLoggedIn()) {
    redirect(dashboardUrl());
}

$pageTitle = "About";
require __DIR__ . "/header.php";
?>

<main class="page-main">
    <div class="page-hero">
        <div class="hero-copy">
            <div class="eyebrow">About HealthNest</div>
            <h1>Simple PHP shopping for wellness products.</h1>
            <p class="lead">HealthNest is a class project e-commerce application for buyers and sellers. It includes catalog browsing, categories, cart management, checkout, simulated payment, account management, inventory, user management, and audit logs.</p>
        </div>

        <div class="hero-panel">
            <h3>Built With Covered Modules</h3>
            <ul class="quick-list">
                <li>PHP pages and includes</li>
                <li>Forms and validation</li>
                <li>Sessions and account levels</li>
                <li>MySQL database records</li>
            </ul>
        </div>
    </div>
</main>

<?php require __DIR__ . "/footer.php"; ?>
