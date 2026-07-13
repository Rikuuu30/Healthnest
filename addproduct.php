<?php

require_once __DIR__ . "/init.php";

requireAdmin();

$message = "";
$categories = getCategories($conn);
$categoryInsightResult = mysqli_query($conn, "
    SELECT c.category_id, c.category_name,
           COUNT(p.product_id) AS product_count,
           COALESCE(AVG(p.price), 0) AS average_price,
           COALESCE(MIN(NULLIF(p.price, 0)), 0) AS min_price,
           COALESCE(MAX(p.price), 0) AS max_price,
           COALESCE(SUM(CASE WHEN p.stock_quantity <= 10 THEN 1 ELSE 0 END), 0) AS low_stock_count
    FROM categories c
    LEFT JOIN products p ON c.category_id = p.category_id
    GROUP BY c.category_id, c.category_name
    ORDER BY c.category_name ASC
");
$categoryInsights = [];
if ($categoryInsightResult) {
    while ($insightRow = mysqli_fetch_assoc($categoryInsightResult)) {
        $categoryInsights[(int) $insightRow["category_id"]] = [
            "name" => $insightRow["category_name"],
            "product_count" => (int) $insightRow["product_count"],
            "average_price" => (float) $insightRow["average_price"],
            "min_price" => (float) $insightRow["min_price"],
            "max_price" => (float) $insightRow["max_price"],
            "low_stock_count" => (int) $insightRow["low_stock_count"],
        ];
    }
}
$existingNamesResult = mysqli_query($conn, "SELECT product_name FROM products ORDER BY product_name ASC");
$existingProductNames = [];
if ($existingNamesResult) {
    while ($nameRow = mysqli_fetch_assoc($existingNamesResult)) {
        $existingProductNames[] = strtolower(trim((string) $nameRow["product_name"]));
    }
}
$values = [
    "product_name" => "",
    "category_id" => "",
    "description" => "",
    "price" => "",
    "stock_quantity" => "",
    "image" => "placeholder.jpg",
    "status" => "active",
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    foreach ($values as $key => $value) {
        $values[$key] = trim($_POST[$key] ?? "");
    }

    $categoryId = (int) $values["category_id"];
    $price = (float) $values["price"];
    $stockQuantity = (int) $values["stock_quantity"];
    $status = strtolower($values["status"]);
    $image = $values["image"] !== "" ? $values["image"] : "placeholder.jpg";

    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        $message = "Your session expired. Please try again.";
    } elseif ($values["product_name"] === "" || $categoryId <= 0 || $values["price"] === "" || $values["stock_quantity"] === "") {
        $message = "Please fill in all required fields.";
    } elseif (!getCategoryById($conn, $categoryId)) {
        $message = "Please select a valid category.";
    } elseif (!is_numeric($values["price"]) || $price <= 0) {
        $message = "Price must be greater than zero.";
    } elseif (!ctype_digit((string) $values["stock_quantity"])) {
        $message = "Stock must be a whole number and cannot be negative.";
    } elseif (!in_array($status, ["active", "inactive"], true)) {
        $message = "Please select a valid status.";
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO products (category_id, product_name, description, price, stock_quantity, image, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())");
        mysqli_stmt_bind_param(
            $stmt,
            "issdiss",
            $categoryId,
            $values["product_name"],
            $values["description"],
            $price,
            $stockQuantity,
            $image,
            $status
        );
        mysqli_stmt_execute($stmt);

        $productId = mysqli_insert_id($conn);
        logAudit($conn, sessionUserId(), "Add Product", "products", $productId, "Added product: " . $values["product_name"]);

        setFlash("success", "Product added successfully.");
        redirect("inventory.php");
    }
}

$pageTitle = "Add Product";
require __DIR__ . "/header.php";
?>

<main class="page-main">
    <div class="seller-page-header">
        <div>
            <div class="eyebrow">Seller Tools</div>
            <h2>Add Product</h2>
            <p>Create a polished catalog item with complete pricing, stock, visibility, and buyer-facing product details.</p>
        </div>
        <a class="button secondary" href="inventory.php">Back to Inventory</a>
    </div>

    <?php if ($message !== ""): ?>
        <div class="error"><?php echo e($message); ?></div>
    <?php endif; ?>

    <div class="seller-form-layout">
        <form class="seller-form" method="post" action="addproduct.php">
            <?php echo csrfField(); ?>
            <div class="seller-form-heading">
                <span class="panel-label">Product Setup</span>
                <h3>Product Information</h3>
            </div>

            <div class="form-grid">
                <div class="full">
                    <label for="product_name">Product Name</label>
                    <input id="product_name" type="text" name="product_name" value="<?php echo e($values["product_name"]); ?>" placeholder="Example: CJC-1295" required>
                </div>

                <div>
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">Select category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo (int) $category["category_id"]; ?>" <?php echo (int) $values["category_id"] === (int) $category["category_id"] ? "selected" : ""; ?>>
                                <?php echo e($category["category_name"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        <option value="active" <?php echo $values["status"] === "active" ? "selected" : ""; ?>>Active</option>
                        <option value="inactive" <?php echo $values["status"] === "inactive" ? "selected" : ""; ?>>Inactive</option>
                    </select>
                </div>

                <div>
                    <label for="price">Price</label>
                    <input id="price" type="number" name="price" min="0.01" step="0.01" value="<?php echo e($values["price"]); ?>" placeholder="0.00" required>
                </div>

                <div>
                    <label for="stock_quantity">Stock</label>
                    <input id="stock_quantity" type="number" name="stock_quantity" min="0" step="1" value="<?php echo e($values["stock_quantity"]); ?>" placeholder="0" required>
                </div>

                <div class="full">
                    <label for="image">Image Filename</label>
                    <input id="image" type="text" name="image" value="<?php echo e($values["image"]); ?>" placeholder="placeholder.jpg">
                </div>

                <div class="full">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Write a concise product description for buyers."><?php echo e($values["description"]); ?></textarea>
                </div>
            </div>

            <button type="submit">Save Product</button>
        </form>

        <aside class="seller-assist-panel">
            <div class="card listing-readiness-card">
                <span class="panel-label">Listing Readiness</span>
                <div class="readiness-score">
                    <strong id="listingScore">0%</strong>
                    <div class="status-meter"><span id="listingScoreBar" style="width: 0%;"></span></div>
                </div>
                <ul class="readiness-list">
                    <li id="checkName">Product name added</li>
                    <li id="checkCategory">Category selected</li>
                    <li id="checkPrice">Valid price entered</li>
                    <li id="checkStock">Opening stock set</li>
                    <li id="checkDescription">Buyer description written</li>
                </ul>
                <p id="duplicateNameNotice" class="assist-warning" hidden>A product with this name already exists.</p>
            </div>

            <div class="card catalog-intel-card">
                <span class="panel-label">Listing Planner</span>
                <h3 id="selectedCategoryName">Select a category</h3>
                <div class="intel-grid">
                    <div>
                        <span>Products</span>
                        <strong id="categoryProductCount">-</strong>
                    </div>
                    <div>
                        <span>Avg Price</span>
                        <strong id="categoryAveragePrice">-</strong>
                    </div>
                    <div>
                        <span>Range</span>
                        <strong id="categoryPriceRange">-</strong>
                    </div>
                    <div>
                        <span>Low Stock</span>
                        <strong id="categoryLowStock">-</strong>
                    </div>
                </div>
                <p id="priceGuidance" class="muted">Choose a category to compare the new product price with your catalog.</p>
                <div class="planner-actions">
                    <button id="useCategoryAveragePrice" type="button">Use Avg Price</button>
                    <button id="setStarterStock" type="button">Set 30 Stock</button>
                </div>
                <div class="planner-divider"></div>
                <h3>Ready-to-List Signals</h3>
                <div class="launch-metrics">
                    <div>
                        <span>Opening Value</span>
                        <strong id="openingStockValue">-</strong>
                    </div>
                    <div>
                        <span>Image</span>
                        <strong id="imageReadiness">Placeholder</strong>
                    </div>
                </div>
                <p id="launchStatusAdvice" class="muted">Complete the required fields to get a visibility recommendation.</p>
                <div class="summary-action-row">
                    <button id="applyRecommendedStatus" type="button">Apply Recommendation</button>
                </div>
                <p class="muted"><span id="descriptionCount">0</span> characters in description.</p>
            </div>
        </aside>
    </div>
</main>

<script>
const categoryInsights = <?php echo json_encode($categoryInsights, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
const existingProductNames = <?php echo json_encode($existingProductNames, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
const moneyFormatter = new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" });

const productNameInput = document.getElementById("product_name");
const categoryInput = document.getElementById("category_id");
const priceInput = document.getElementById("price");
const stockInput = document.getElementById("stock_quantity");
const imageInput = document.getElementById("image");
const statusInput = document.getElementById("status");
const descriptionInput = document.getElementById("description");
const scoreText = document.getElementById("listingScore");
const scoreBar = document.getElementById("listingScoreBar");
const duplicateNotice = document.getElementById("duplicateNameNotice");
const descriptionCount = document.getElementById("descriptionCount");
const openingStockValue = document.getElementById("openingStockValue");
const imageReadiness = document.getElementById("imageReadiness");
const launchStatusAdvice = document.getElementById("launchStatusAdvice");
const applyRecommendedStatus = document.getElementById("applyRecommendedStatus");
const useCategoryAveragePrice = document.getElementById("useCategoryAveragePrice");
const setStarterStock = document.getElementById("setStarterStock");
let recommendedStatus = "inactive";

const checks = {
    name: document.getElementById("checkName"),
    category: document.getElementById("checkCategory"),
    price: document.getElementById("checkPrice"),
    stock: document.getElementById("checkStock"),
    description: document.getElementById("checkDescription"),
};

function setCheck(element, isReady) {
    if (!element) {
        return;
    }

    element.classList.toggle("is-ready", isReady);
}

function updateReadiness() {
    const name = productNameInput.value.trim();
    const selectedCategory = categoryInput.value !== "";
    const price = Number(priceInput.value);
    const stock = Number(stockInput.value);
    const description = descriptionInput.value.trim();

    const states = [
        name.length >= 3,
        selectedCategory,
        Number.isFinite(price) && price > 0,
        stockInput.value.trim() !== "" && Number.isInteger(stock) && stock >= 0,
        description.length >= 30,
    ];

    setCheck(checks.name, states[0]);
    setCheck(checks.category, states[1]);
    setCheck(checks.price, states[2]);
    setCheck(checks.stock, states[3]);
    setCheck(checks.description, states[4]);

    const score = Math.round((states.filter(Boolean).length / states.length) * 100);
    scoreText.textContent = `${score}%`;
    scoreBar.style.width = `${score}%`;
    descriptionCount.textContent = description.length;

    duplicateNotice.hidden = !existingProductNames.includes(name.toLowerCase());
    updateLaunchPlanner(score, price, stock);
}

function updateLaunchPlanner(score, price, stock) {
    const hasValidInventoryValue = Number.isFinite(price) && price > 0 && Number.isInteger(stock) && stock >= 0 && stockInput.value.trim() !== "";
    openingStockValue.textContent = hasValidInventoryValue ? moneyFormatter.format(price * stock) : "-";

    const imageName = imageInput.value.trim();
    const usesPlaceholder = imageName === "" || imageName.toLowerCase() === "placeholder.jpg";
    imageReadiness.textContent = usesPlaceholder ? "Placeholder" : "Custom";

    recommendedStatus = score >= 80 && !usesPlaceholder ? "active" : "inactive";

    if (recommendedStatus === "active") {
        launchStatusAdvice.textContent = "This product looks ready to list as active.";
    } else if (score >= 80) {
        launchStatusAdvice.textContent = "Details look strong. Add a custom image before making it active.";
    } else {
        launchStatusAdvice.textContent = "Keep this inactive until the listing readiness score improves.";
    }
}

function updateCategoryIntel() {
    const categoryId = categoryInput.value;
    const insight = categoryInsights[categoryId];
    const price = Number(priceInput.value);

    document.getElementById("selectedCategoryName").textContent = insight ? insight.name : "Select a category";
    document.getElementById("categoryProductCount").textContent = insight ? insight.product_count : "-";
    document.getElementById("categoryAveragePrice").textContent = insight ? moneyFormatter.format(insight.average_price) : "-";
    document.getElementById("categoryPriceRange").textContent = insight && insight.max_price > 0 ? `${moneyFormatter.format(insight.min_price)} - ${moneyFormatter.format(insight.max_price)}` : "-";
    document.getElementById("categoryLowStock").textContent = insight ? insight.low_stock_count : "-";

    const guidance = document.getElementById("priceGuidance");
    if (!insight || !Number.isFinite(price) || price <= 0 || insight.average_price <= 0) {
        guidance.textContent = "Choose a category to compare the new product price with your catalog.";
        return;
    }

    const difference = Math.round(((price - insight.average_price) / insight.average_price) * 100);
    if (Math.abs(difference) <= 10) {
        guidance.textContent = "This price is close to the category average.";
    } else if (difference > 10) {
        guidance.textContent = `This price is ${difference}% above the category average.`;
    } else {
        guidance.textContent = `This price is ${Math.abs(difference)}% below the category average.`;
    }
}

[productNameInput, categoryInput, priceInput, stockInput, imageInput, descriptionInput].forEach((input) => {
    input.addEventListener("input", () => {
        updateReadiness();
        updateCategoryIntel();
    });
    input.addEventListener("change", () => {
        updateReadiness();
        updateCategoryIntel();
    });
});

if (applyRecommendedStatus) {
    applyRecommendedStatus.addEventListener("click", () => {
        statusInput.value = recommendedStatus;
        updateReadiness();
        updateCategoryIntel();
    });
}

if (useCategoryAveragePrice) {
    useCategoryAveragePrice.addEventListener("click", () => {
        const insight = categoryInsights[categoryInput.value];
        if (insight && insight.average_price > 0) {
            priceInput.value = insight.average_price.toFixed(2);
            updateReadiness();
            updateCategoryIntel();
        }
    });
}

if (setStarterStock) {
    setStarterStock.addEventListener("click", () => {
        stockInput.value = "30";
        updateReadiness();
        updateCategoryIntel();
    });
}

updateReadiness();
updateCategoryIntel();
</script>

<?php require __DIR__ . "/footer.php"; ?>
