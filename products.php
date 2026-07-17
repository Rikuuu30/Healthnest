<?php
require_once __DIR__ . "/init.php";
if (isLoggedIn() && isAdmin()) {
    redirect("seller_dashboard.php");
}
$categoryId = filter_input(INPUT_GET, "category", FILTER_VALIDATE_INT);
$search = trim((string) ($_GET["q"] ?? ""));
$sort = (string) ($_GET["sort"] ?? "name");
$stockFilter = (string) ($_GET["stock"] ?? "all");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("error", "Your session expired. Please try again.");
        redirect("products.php");
    }

    if (!isLoggedIn()) {
        setFlash("error", "Please log in before adding products to your cart.");
        redirect("login.php");
    }

    $productId = filter_input(INPUT_POST, "product_id", FILTER_VALIDATE_INT);
    $quantity = max(1, (int) ($_POST["quantity"] ?? 1));
    $product = $productId ? getProductById($conn, $productId) : null;

    if (!$product || strtolower((string) $product["status"]) !== "active") {
        setFlash("error", "Product is no longer available.");
    } elseif ($quantity > (int) $product["stock_quantity"]) {
        setFlash("error", "That quantity is higher than the available stock.");
    } else {
        cartAdd($conn, sessionUserId(), $productId, $quantity);
        setFlash("success", $product["product_name"] . " was added to your cart.");
    }

    $query = $_GET ? "?" . http_build_query($_GET) : "";
    redirect("products.php" . $query);
}

$categories = getCategories($conn);
$products = getProducts($conn, $categoryId);
$selectedCategory = $categoryId ? getCategoryById($conn, $categoryId) : null;
$products = array_values(array_filter($products, function ($product) use ($search, $stockFilter) {
    $matchesSearch = $search === ""
        || stripos((string) $product["product_name"], $search) !== false
        || stripos((string) ($product["description"] ?? ""), $search) !== false
        || stripos((string) ($product["category_name"] ?? ""), $search) !== false;
    $stock = (int) ($product["stock_quantity"] ?? 0);
    $matchesStock = $stockFilter === "all"
        || ($stockFilter === "available" && $stock > 0)
        || ($stockFilter === "low" && $stock > 0 && $stock <= 10)
        || ($stockFilter === "out" && $stock <= 0);

    return $matchesSearch && $matchesStock;
}));

usort($products, function ($a, $b) use ($sort) {
    if ($sort === "price_low") {
        return ((float) $a["price"]) <=> ((float) $b["price"]);
    }
    if ($sort === "price_high") {
        return ((float) $b["price"]) <=> ((float) $a["price"]);
    }
    if ($sort === "stock") {
        return ((int) $b["stock_quantity"]) <=> ((int) $a["stock_quantity"]);
    }
    return strcasecmp((string) $a["product_name"], (string) $b["product_name"]);
});

$availableCount = count(array_filter($products, fn($product) => (int) $product["stock_quantity"] > 0));
$lowStockCount = count(array_filter($products, fn($product) => (int) $product["stock_quantity"] > 0 && (int) $product["stock_quantity"] <= 10));
$pageTitle = "Products";
require __DIR__ . "/header.php";
?>
<main class="page-main">
   <section class="seller-page-header buyer-page-header">
        <div>
            <span class="panel-label">Buyer Catalog</span>
            <h2>Products</h2>
            <p>Browse active HealthNest products with search, category, stock, and price sorting controls.</p>
        </div>
        <a class="button secondary" href="cart.php">View Cart</a>
    </section>

    <section class="dashboard-stat-strip buyer-stat-strip">
        <div>   
            <span>Results</span>
            <strong><?php echo count($products); ?></strong>
        </div>
        <div>
            <span>Available</span>
            <strong><?php echo $availableCount; ?></strong>
        </div>
        <div>
            <span>Low Stock</span>
            <strong><?php echo $lowStockCount; ?></strong>
        </div>
        <div>
            <span>Categories</span>
            <strong><?php echo count($categories); ?></strong>
        </div>
    </section>

    <form method="get" action="products.php" class="catalog-control-shell buyer-catalog-controls">
        <div class="catalog-title-block">
            <span class="panel-label">Catalog Controls</span>
            <h3>Find Products</h3>
        </div>
        <div class="buyer-filter-grid">
            <div>
                <label for="q">Search</label>
                <input id="q" type="search" name="q" value="<?php echo e($search); ?>" placeholder="Search product, category, or description">
            </div>
            <div>
                <label id="category-label" for="category">Category</label>
                <div class="role-select catalog-filter-select" data-product-select>
                    <select id="category" name="category" aria-labelledby="category-label">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo (int) $category["category_id"]; ?>" <?php echo $categoryId === (int) $category["category_id"] ? "selected" : ""; ?>>
                                <?php echo e($category["category_name"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="role-select-trigger" type="button" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="category-label category-select-value">
                        <strong id="category-select-value"><?php echo $selectedCategory ? e($selectedCategory["category_name"]) : "All Categories"; ?></strong>
                        <span class="role-select-chevron" aria-hidden="true"></span>
                    </button>
                    <div class="role-select-menu" role="listbox" aria-labelledby="category-label" hidden>
                        <button type="button" class="role-select-option" role="option" data-select-value="" aria-selected="<?php echo $categoryId ? "false" : "true"; ?>">All Categories</button>
                        <?php foreach ($categories as $category): ?>
                            <button type="button" class="role-select-option" role="option" data-select-value="<?php echo (int) $category["category_id"]; ?>" aria-selected="<?php echo $categoryId === (int) $category["category_id"] ? "true" : "false"; ?>">
                                <?php echo e($category["category_name"]); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div>
                <label id="stock-label" for="stock">Stock</label>
                <div class="role-select catalog-filter-select" data-product-select>
                    <select id="stock" name="stock" aria-labelledby="stock-label">
                        <option value="all" <?php echo $stockFilter === "all" ? "selected" : ""; ?>>All Stock</option>
                        <option value="available" <?php echo $stockFilter === "available" ? "selected" : ""; ?>>Available</option>
                        <option value="low" <?php echo $stockFilter === "low" ? "selected" : ""; ?>>Low Stock</option>
                        <option value="out" <?php echo $stockFilter === "out" ? "selected" : ""; ?>>Out of Stock</option>
                    </select>
                    <button class="role-select-trigger" type="button" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="stock-label stock-select-value">
                        <strong id="stock-select-value"><?php echo e($stockFilter === "available" ? "Available" : ($stockFilter === "low" ? "Low Stock" : ($stockFilter === "out" ? "Out of Stock" : "All Stock"))); ?></strong>
                        <span class="role-select-chevron" aria-hidden="true"></span>
                    </button>
                    <div class="role-select-menu" role="listbox" aria-labelledby="stock-label" hidden>
                        <button type="button" class="role-select-option" role="option" data-select-value="all" aria-selected="<?php echo $stockFilter === "all" ? "true" : "false"; ?>">All Stock</button>
                        <button type="button" class="role-select-option" role="option" data-select-value="available" aria-selected="<?php echo $stockFilter === "available" ? "true" : "false"; ?>">Available</button>
                        <button type="button" class="role-select-option" role="option" data-select-value="low" aria-selected="<?php echo $stockFilter === "low" ? "true" : "false"; ?>">Low Stock</button>
                        <button type="button" class="role-select-option" role="option" data-select-value="out" aria-selected="<?php echo $stockFilter === "out" ? "true" : "false"; ?>">Out of Stock</button>
                    </div>
                </div>
            </div>
            <div>
                <label id="sort-label" for="sort">Sort</label>
                <div class="role-select catalog-filter-select" data-product-select>
                    <select id="sort" name="sort" aria-labelledby="sort-label">
                        <option value="name" <?php echo $sort === "name" ? "selected" : ""; ?>>Name</option>
                        <option value="price_low" <?php echo $sort === "price_low" ? "selected" : ""; ?>>Price: Low to High</option>
                        <option value="price_high" <?php echo $sort === "price_high" ? "selected" : ""; ?>>Price: High to Low</option>
                        <option value="stock" <?php echo $sort === "stock" ? "selected" : ""; ?>>Stock</option>
                    </select>
                    <button class="role-select-trigger" type="button" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="sort-label sort-select-value">
                        <strong id="sort-select-value"><?php echo e($sort === "price_low" ? "Price: Low to High" : ($sort === "price_high" ? "Price: High to Low" : ($sort === "stock" ? "Stock" : "Name"))); ?></strong>
                        <span class="role-select-chevron" aria-hidden="true"></span>
                    </button>
                    <div class="role-select-menu" role="listbox" aria-labelledby="sort-label" hidden>
                        <button type="button" class="role-select-option" role="option" data-select-value="name" aria-selected="<?php echo $sort === "name" ? "true" : "false"; ?>">Name</button>
                        <button type="button" class="role-select-option" role="option" data-select-value="price_low" aria-selected="<?php echo $sort === "price_low" ? "true" : "false"; ?>">Price: Low to High</button>
                        <button type="button" class="role-select-option" role="option" data-select-value="price_high" aria-selected="<?php echo $sort === "price_high" ? "true" : "false"; ?>">Price: High to Low</button>
                        <button type="button" class="role-select-option" role="option" data-select-value="stock" aria-selected="<?php echo $sort === "stock" ? "true" : "false"; ?>">Stock</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="filter-actions">
            <button type="submit">Apply Filters</button>
            <a class="button secondary" href="products.php">Clear</a>
        </div>
    </form>
    <?php if ($selectedCategory): ?>
        <div class="panel">
            <h3><?php echo e($selectedCategory["category_name"]); ?></h3>
            <p><?php echo e($selectedCategory["description"]); ?></p>
        </div>
    <?php endif; ?>
    <section class="buyer-shop-assist">
        <div>
            <span class="buyer-kicker">Buyer Tools</span>
            <strong>Compare up to 3 products before opening a detail page.</strong>
            <p>Use the compare checkbox on product cards to quickly review price, category, and stock side by side.</p>
        </div>
        <div class="buyer-shop-assist-points">
            <span>Stock-aware quick add</span>
            <span>Category filtering</span>
            <span>Price sorting</span>
        </div>
    </section>
    <?php if (count($products) > 0): ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <?php 
                $imagePath = "images/products/" . productImageFilename($product["image"] ?? "");
                ?>
                <article class="card product-card buyer-product-card" data-product-card data-name="<?php echo e($product["product_name"]); ?>" data-category="<?php echo e($product["category_name"] ?? "Uncategorized"); ?>" data-price="<?php echo e(formatPrice($product["price"])); ?>" data-stock="<?php echo (int) $product["stock_quantity"]; ?>">
                    <div class="product-image-wrap">
                        <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                             alt="<?php echo e($product["product_name"]); ?>" 
                             class="product-image"
                             onerror="this.closest('.product-image-wrap').classList.add('image-missing'); this.remove();">
                    </div>
                    <span class="badge product-category-label"><?php echo e($product["category_name"] ?? "Uncategorized"); ?></span>
                    <h3><?php echo e($product["product_name"]); ?></h3>
                    <p><?php echo e($product["description"] ?: "No description available."); ?></p>
                    <p class="price"><?php echo formatPrice($product["price"]); ?></p>
                    <p class="muted">Stock: <?php echo (int) $product["stock_quantity"]; ?></p>
                    <div class="buyer-card-actions">
                        <a class="buyer-view-product-action" href="product.php?id=<?php echo (int) $product["product_id"]; ?>">View Product</a>
                        <label class="buyer-compare-control">
                            <input type="checkbox" data-compare-product>
                            <span>Compare</span>
                        </label>
                        <?php if (isLoggedIn() && (int) $product["stock_quantity"] > 0): ?>
                            <form method="post" action="products.php?<?php echo e(http_build_query($_GET)); ?>">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="product_id" value="<?php echo (int) $product["product_id"]; ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit">Quick Add</button>
                            </form>
                        <?php elseif ((int) $product["stock_quantity"] <= 0): ?>
                            <span class="stock-badge stock-low">Out of stock</span>
                        <?php else: ?>
                            <a class="button secondary" href="login.php">Login to Buy</a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <p>No active products found.</p>
        </div>
    <?php endif; ?>

    <aside class="buyer-compare-tray" id="buyerCompareTray" hidden>
        <div class="buyer-compare-head">
            <strong>Compare Products</strong>
            <button type="button" id="buyerCompareClear">Clear</button>
        </div>
        <div class="buyer-compare-list" id="buyerCompareList"></div>
    </aside>
</main>
<script>
(() => {
    document.querySelectorAll("[data-product-select]").forEach((selectRoot) => {
        const nativeSelect = selectRoot.querySelector("select");
        const trigger = selectRoot.querySelector(".role-select-trigger");
        const menu = selectRoot.querySelector(".role-select-menu");
        const value = selectRoot.querySelector(".role-select-trigger strong");
        const options = Array.from(selectRoot.querySelectorAll(".role-select-option"));

        if (!nativeSelect || !trigger || !menu || !value || options.length === 0) {
            return;
        }

        const syncSelect = (selectedValue) => {
            const selectedOption = options.find((option) => option.dataset.selectValue === selectedValue);

            if (!selectedOption) {
                return;
            }

            nativeSelect.value = selectedValue;
            value.textContent = selectedOption.textContent.trim();
            options.forEach((option) => {
                option.setAttribute("aria-selected", option === selectedOption ? "true" : "false");
            });
        };

        const openMenu = () => {
            menu.hidden = false;
            trigger.setAttribute("aria-expanded", "true");
            selectRoot.classList.add("is-open");

            const selectedOption = options.find((option) => option.getAttribute("aria-selected") === "true");
            (selectedOption || options[0]).focus();
        };

        const closeMenu = (restoreFocus) => {
            menu.hidden = true;
            trigger.setAttribute("aria-expanded", "false");
            selectRoot.classList.remove("is-open");

            if (restoreFocus) {
                trigger.focus();
            }
        };

        selectRoot.classList.add("custom-select-ready");
        nativeSelect.tabIndex = -1;
        nativeSelect.setAttribute("aria-hidden", "true");
        syncSelect(nativeSelect.value);

        trigger.addEventListener("click", () => {
            if (menu.hidden) {
                openMenu();
            } else {
                closeMenu(false);
            }
        });

        trigger.addEventListener("keydown", (event) => {
            if (event.key === "ArrowDown" || event.key === "ArrowUp") {
                event.preventDefault();
                openMenu();
            }
        });

        options.forEach((option, index) => {
            option.addEventListener("click", () => {
                syncSelect(option.dataset.selectValue);
                nativeSelect.dispatchEvent(new Event("change", { bubbles: true }));
                closeMenu(true);
            });

            option.addEventListener("keydown", (event) => {
                let nextIndex = index;

                if (event.key === "ArrowDown") {
                    nextIndex = (index + 1) % options.length;
                } else if (event.key === "ArrowUp") {
                    nextIndex = (index - 1 + options.length) % options.length;
                } else if (event.key === "Home") {
                    nextIndex = 0;
                } else if (event.key === "End") {
                    nextIndex = options.length - 1;
                } else if (event.key === "Escape") {
                    event.preventDefault();
                    closeMenu(true);
                    return;
                } else if (event.key === "Tab") {
                    closeMenu(false);
                    return;
                } else {
                    return;
                }

                event.preventDefault();
                options[nextIndex].focus();
            });
        });

        nativeSelect.addEventListener("change", () => {
            syncSelect(nativeSelect.value);
        });

        document.addEventListener("click", (event) => {
            if (!menu.hidden && !selectRoot.contains(event.target)) {
                closeMenu(false);
            }
        });
    });

    const tray = document.getElementById("buyerCompareTray");
    const list = document.getElementById("buyerCompareList");
    const clear = document.getElementById("buyerCompareClear");
    const checks = Array.from(document.querySelectorAll("[data-compare-product]"));

    if (!tray || !list || checks.length === 0) {
        return;
    }

    function renderCompare() {
        const selected = checks
            .filter((check) => check.checked)
            .map((check) => check.closest("[data-product-card]"));

        tray.hidden = selected.length === 0;
        list.innerHTML = "";

        selected.forEach((card) => {
            const item = document.createElement("div");
            item.innerHTML = `<strong>${card.dataset.name}</strong><span>${card.dataset.category}</span><em>${card.dataset.price}</em><small>${card.dataset.stock} in stock</small>`;
            list.appendChild(item);
        });
    }

    checks.forEach((check) => {
        check.addEventListener("change", () => {
            const selected = checks.filter((item) => item.checked);
            if (selected.length > 3) {
                check.checked = false;
                window.alert("Compare up to 3 products at a time.");
            }
            renderCompare();
        });
    });

    clear.addEventListener("click", () => {
        checks.forEach((check) => {
            check.checked = false;
        });
        renderCompare();
    });
})();
</script>
<?php require __DIR__ . "/footer.php"; ?>
