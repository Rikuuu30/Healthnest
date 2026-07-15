<?php
require_once __DIR__ . "/init.php";
if (isLoggedIn() && isAdmin()) {
    redirect("seller_dashboard.php");
}
$categoryId = filter_input(INPUT_GET, "category", FILTER_VALIDATE_INT);
$categories = getCategories($conn);
$products = getProducts($conn, $categoryId);
$selectedCategory = $categoryId ? getCategoryById($conn, $categoryId) : null;
$pageTitle = "Products";
require __DIR__ . "/header.php";
?>
<main class="page-main">
    <div class="admin-bar">
        <div>
            <div class="eyebrow">Buyer Catalog</div>
            <h2>Products</h2>
            <p>Browse active HealthNest products and filter by wellness category.</p>
        </div>
    </div>
    <form method="get" action="products.php" class="catalog-filter-form">
        <label id="category-label">Category</label>
        <div class="catalog-select" data-catalog-select>
            <select id="category" name="category" aria-labelledby="category-label">
                <option value="">All Categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo (int) $category["category_id"]; ?>" <?php echo $categoryId === (int) $category["category_id"] ? "selected" : ""; ?>>
                        <?php echo e($category["category_name"]); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="catalog-select-trigger" type="button" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="category-label catalog-select-value">
                <span id="catalog-select-value"><?php echo e($selectedCategory["category_name"] ?? "All Categories"); ?></span>
                <span class="catalog-select-chevron" aria-hidden="true"></span>
            </button>
            <div class="catalog-select-menu" role="listbox" aria-labelledby="category-label" hidden>
                <button type="button" class="catalog-select-option" role="option" data-category-value="" aria-selected="<?php echo !$categoryId ? "true" : "false"; ?>">
                    <span>All Categories</span>
                </button>
                <?php foreach ($categories as $category): ?>
                    <button type="button" class="catalog-select-option" role="option" data-category-value="<?php echo (int) $category["category_id"]; ?>" aria-selected="<?php echo $categoryId === (int) $category["category_id"] ? "true" : "false"; ?>">
                        <span><?php echo e($category["category_name"]); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <button type="submit">Filter</button>
    </form>
    <script>
        (function () {
            "use strict";
            var picker = document.querySelector("[data-catalog-select]");
            if (!picker) { return; }
            var select = picker.querySelector("select");
            var trigger = picker.querySelector(".catalog-select-trigger");
            var menu = picker.querySelector(".catalog-select-menu");
            var valueLabel = picker.querySelector("#catalog-select-value");
            var options = Array.prototype.slice.call(picker.querySelectorAll(".catalog-select-option"));
            var syncSelection = function (value) {
                var selectedOption = options.find(function (option) { return option.dataset.categoryValue === value; });
                if (!selectedOption) { return; }
                select.value = value;
                valueLabel.textContent = selectedOption.querySelector("span").textContent;
                options.forEach(function (option) { option.setAttribute("aria-selected", option === selectedOption ? "true" : "false"); });
            };
            var openMenu = function () {
                menu.hidden = false; picker.classList.add("is-open"); trigger.setAttribute("aria-expanded", "true");
                var selectedOption = options.find(function (option) { return option.getAttribute("aria-selected") === "true"; });
                (selectedOption || options[0]).focus();
            };
            var closeMenu = function (restoreFocus) {
                menu.hidden = true; picker.classList.remove("is-open"); trigger.setAttribute("aria-expanded", "false");
                if (restoreFocus) { trigger.focus(); }
            };
            picker.classList.add("custom-select-ready");
            select.tabIndex = -1; select.setAttribute("aria-hidden", "true");
            syncSelection(select.value);
            trigger.addEventListener("click", function () { if (menu.hidden) { openMenu(); } else { closeMenu(false); } });
            trigger.addEventListener("keydown", function (event) {
                if (event.key === "ArrowDown" || event.key === "ArrowUp") { event.preventDefault(); openMenu(); }
            });
            options.forEach(function (option, index) {
                option.addEventListener("click", function () { syncSelection(option.dataset.categoryValue); select.dispatchEvent(new Event("change", { bubbles: true })); closeMenu(true); });
                option.addEventListener("keydown", function (event) {
                    var nextIndex = index;
                    if (event.key === "ArrowDown") { nextIndex = (index + 1) % options.length; }
                    else if (event.key === "ArrowUp") { nextIndex = (index - 1 + options.length) % options.length; }
                    else if (event.key === "Home") { nextIndex = 0; }
                    else if (event.key === "End") { nextIndex = options.length - 1; }
                    else if (event.key === "Escape") { event.preventDefault(); closeMenu(true); return; }
                    else if (event.key === "Tab") { closeMenu(false); return; }
                    else { return; }
                    event.preventDefault(); options[nextIndex].focus();
                });
            });
            select.addEventListener("change", function () { syncSelection(select.value); });
            document.addEventListener("click", function (event) { if (!menu.hidden && !picker.contains(event.target)) { closeMenu(false); } });
        }());
    </script>
    <?php if ($selectedCategory): ?>
        <div class="panel">
            <h3><?php echo e($selectedCategory["category_name"]); ?></h3>
            <p><?php echo e($selectedCategory["description"]); ?></p>
        </div>
    <?php endif; ?>
    <?php if (count($products) > 0): ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <?php 
                // 1. Get the exact filename from the database 'image' column
                $dbImage = !empty($product["image"]) ? $product["image"] : "placeholder.jpg";
                // 2. Prepend the folder path
                $imagePath = "images/products/" . $dbImage;
                ?>
                <div class="card product-card">
                    <div class="product-image-wrap">
                        <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                             alt="<?php echo e($product["product_name"]); ?>" 
                             class="product-image"
                             onerror="this.src='images/placeholder.png'; this.onerror=null;">
                    </div>
                    <span class="badge"><?php echo e($product["category_name"] ?? "Uncategorized"); ?></span>
                    <h3><?php echo e($product["product_name"]); ?></h3>
                    <p><?php echo e($product["description"] ?: "No description available."); ?></p>
                    <p class="price"><?php echo formatPrice($product["price"]); ?></p>
                    <p class="muted">Stock: <?php echo (int) $product["stock_quantity"]; ?></p>
                    <a href="product.php?id=<?php echo (int) $product["product_id"]; ?>">View Product</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <p>No active products found.</p>
        </div>
    <?php endif; ?>
</main>
<?php require __DIR__ . "/footer.php"; ?>
