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

$existingNamesResult = mysqli_query($conn, "SELECT product_name FROM products WHERE product_id <> " . (int) $productId . " ORDER BY product_name ASC");
$existingProductNames = [];
if ($existingNamesResult) {
    while ($nameRow = mysqli_fetch_assoc($existingNamesResult)) {
        $existingProductNames[] = strtolower(trim((string) $nameRow["product_name"]));
    }
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

$values = [
    "product_name" => $product["product_name"] ?? "",
    "category_id" => $product["category_id"] ?? "",
    "description" => $product["description"] ?? "",
    "price" => $product["price"] ?? "",
    "stock_quantity" => $product["stock_quantity"] ?? "",
    "image" => $product["image"] ?? "placeholder.jpg",
    "status" => $product["status"] ?? "active",
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    foreach ($values as $key => $value) {
        $values[$key] = trim($_POST[$key] ?? "");
    }

    $categoryId = (int) $values["category_id"];
    $price = (float) $values["price"];
    $stockQuantity = (int) $values["stock_quantity"];
    $status = strtolower($values["status"]);
    $currentImage = productImageFilename($product["image"] ?? "placeholder.jpg");

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
        $imageUpload = handleProductImageUpload("product_image", $currentImage);

        if (!$imageUpload["success"]) {
            $message = $imageUpload["message"];
        } else {
            $image = $imageUpload["filename"];
            $stmt = mysqli_prepare($conn, "UPDATE products SET category_id = ?, product_name = ?, description = ?, price = ?, stock_quantity = ?, image = ?, status = ? WHERE product_id = ?");
            mysqli_stmt_bind_param(
                $stmt,
                "issdissi",
                $categoryId,
                $values["product_name"],
                $values["description"],
                $price,
                $stockQuantity,
                $image,
                $status,
                $productId
            );
            mysqli_stmt_execute($stmt);

            $details = "Updated product: " . $values["product_name"];
            if ((int) $product["stock_quantity"] !== $stockQuantity) {
                $details .= " | Stock changed from " . (int) $product["stock_quantity"] . " to " . $stockQuantity;
            }
            if ((float) $product["price"] !== $price) {
                $details .= " | Price changed from " . formatPrice($product["price"]) . " to " . formatPrice($price);
            }
            if (productImageFilename($product["image"] ?? "") !== $image) {
                $details .= " | Product image updated";
            }
            logAudit($conn, sessionUserId(), "Edit Product", "products", $productId, $details);

            setFlash("success", "Product updated successfully.");
            redirect("inventory.php");
        }
    }
}

$currentStock = (int) $values["stock_quantity"];
$currentPrice = (float) $values["price"];
$inventoryValue = $currentPrice * $currentStock;
$sellThrough = ($unitsSold + $currentStock) > 0 ? round(($unitsSold / ($unitsSold + $currentStock)) * 100) : 0;
$imageValue = trim((string) $values["image"]);
$hasCustomImage = $imageValue !== "" && strtolower($imageValue) !== "placeholder.jpg";
$currentImageFilename = productImageFilename($values["image"]);
$currentImagePath = "images/products/" . $currentImageFilename;
$currentImageExists = $hasCustomImage && is_file(__DIR__ . "/" . $currentImagePath);

$pageTitle = "Edit Product";
require __DIR__ . "/header.php";
?>

<main class="page-main edit-product-page">
    <div class="seller-page-header">
        <div>
            <div class="eyebrow">Seller Tools</div>
            <h2>Edit Product</h2>
            <p>Update product details, review listing quality, compare category pricing, and plan stock changes before saving.</p>
        </div>
        <div class="filter-actions">
            <a class="button secondary" href="inventory.php">Back to Inventory</a>
        </div>
    </div>

    <?php if ($message !== ""): ?>
        <div class="error"><?php echo e($message); ?></div>
    <?php endif; ?>

    <div class="seller-form-layout edit-product-layout">
        <form class="seller-form edit-product-form" method="post" action="editproduct.php?id=<?php echo (int) $productId; ?>" enctype="multipart/form-data">
            <?php echo csrfField(); ?>
            <input id="image" type="hidden" name="image" value="<?php echo e(productImageFilename($values["image"])); ?>">
            <div class="seller-form-heading">
                <span class="panel-label">Product #<?php echo (int) $productId; ?></span>
                <h3>Product Information</h3>
            </div>

            <div class="product-edit-summary">
                <div>
                    <span>Inventory Value</span>
                    <strong id="summaryInventoryValue"><?php echo formatPrice($inventoryValue); ?></strong>
                </div>
                <div>
                    <span>Sell-through</span>
                    <strong><?php echo $sellThrough; ?>%</strong>
                </div>
                <div>
                    <span>Orders</span>
                    <strong><?php echo $orderCount; ?></strong>
                </div>
            </div>

            <div class="form-grid">
                <div class="full">
                    <label for="product_name">Product Name</label>
                    <input id="product_name" type="text" name="product_name" value="<?php echo e($values["product_name"]); ?>" required>
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
                    <input id="price" type="number" name="price" min="0.01" step="0.01" value="<?php echo e($values["price"]); ?>" required>
                </div>

                <div>
                    <label for="stock_quantity">Stock</label>
                    <div class="stock-editor-row">
                        <button type="button" data-stock-adjust="-5">-5</button>
                        <input id="stock_quantity" type="number" name="stock_quantity" min="0" step="1" value="<?php echo e($values["stock_quantity"]); ?>" required>
                        <button type="button" data-stock-adjust="5">+5</button>
                    </div>
                    <p id="stockAdvice" class="field-hint">Stock changes are recorded in the audit log.</p>
                </div>

                <div class="full">
                    <label for="product_image">Product Image</label>
                    <div class="product-upload-control">
                        <div class="product-upload-preview <?php echo $currentImageExists ? "has-image" : ""; ?>" id="productImagePreview" aria-hidden="true">
                            <span><?php echo $currentImageExists ? "Current" : "No image"; ?></span>
                            <img id="productImagePreviewImg" src="<?php echo $currentImageExists ? e($currentImagePath) : ""; ?>" alt="" <?php echo $currentImageExists ? "" : "hidden"; ?>>
                        </div>
                        <div class="product-upload-copy">
                            <strong id="productImageFileName"><?php echo $currentImageExists ? e($currentImageFilename) : "Choose a replacement image"; ?></strong>
                            <span>JPG, PNG, or WebP up to 5 MB. Leave empty to keep the current image.</span>
                            <label class="product-upload-button" for="product_image">Browse Image</label>
                        </div>
                    </div>
                    <input class="product-upload-input" id="product_image" type="file" name="product_image" accept="image/jpeg,image/png,image/webp">
                </div>

                <div class="full">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Write a concise product description for buyers."><?php echo e($values["description"]); ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit">Update Product</button>
                <a href="inventory.php">Cancel</a>
            </div>
        </form>

        <aside class="seller-assist-panel">
            <div class="card listing-readiness-card">
                <span class="panel-label">Listing Readiness</span>
                <div class="readiness-score">
                    <strong id="listingScore">0%</strong>
                    <div class="status-meter"><span id="listingScoreBar" style="width: 0%;"></span></div>
                </div>
                <ul class="readiness-list">
                    <li id="checkName">Product name is clear</li>
                    <li id="checkCategory">Category selected</li>
                    <li id="checkPrice">Valid price entered</li>
                    <li id="checkStock">Stock is available</li>
                    <li id="checkDescription">Buyer description is useful</li>
                    <li id="checkImage">Custom image added</li>
                </ul>
                <p id="duplicateNameNotice" class="assist-warning" hidden>A different product already uses this name.</p>
                <p id="launchStatusAdvice" class="muted">Update fields to get a visibility recommendation.</p>
                <div class="summary-action-row">
                    <button id="applyRecommendedStatus" type="button">Apply Recommended Status</button>
                </div>
            </div>

            <div class="card catalog-intel-card">
                <span class="panel-label">Product Intel</span>
                <h3 id="selectedCategoryName"><?php echo e($product["category_name"] ?? "Category"); ?></h3>
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
                <p id="priceGuidance" class="muted">Compare this price with the selected category.</p>
                <div class="planner-actions">
                    <button id="useCategoryAveragePrice" type="button">Use Avg Price</button>
                    <button id="setRestockTarget" type="button">Set 30 Stock</button>
                </div>
                <div class="planner-divider"></div>
                <h3>Current Performance</h3>
                <div class="launch-metrics">
                    <div>
                        <span>Units Sold</span>
                        <strong><?php echo $unitsSold; ?></strong>
                    </div>
                    <div>
                        <span>Sales Value</span>
                        <strong><?php echo formatPrice($salesValue); ?></strong>
                    </div>
                    <div>
                        <span>Stock Value</span>
                        <strong id="openingStockValue"><?php echo formatPrice($inventoryValue); ?></strong>
                    </div>
                    <div>
                        <span>Image</span>
                        <strong id="imageReadiness"><?php echo $hasCustomImage ? "Custom" : "Placeholder"; ?></strong>
                    </div>
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
const productImageInput = document.getElementById("product_image");
const statusInput = document.getElementById("status");
const descriptionInput = document.getElementById("description");
const scoreText = document.getElementById("listingScore");
const scoreBar = document.getElementById("listingScoreBar");
const duplicateNotice = document.getElementById("duplicateNameNotice");
const descriptionCount = document.getElementById("descriptionCount");
const openingStockValue = document.getElementById("openingStockValue");
const summaryInventoryValue = document.getElementById("summaryInventoryValue");
const imageReadiness = document.getElementById("imageReadiness");
const productImageFileName = document.getElementById("productImageFileName");
const productImagePreview = document.getElementById("productImagePreview");
const productImagePreviewImg = document.getElementById("productImagePreviewImg");
const launchStatusAdvice = document.getElementById("launchStatusAdvice");
const applyRecommendedStatus = document.getElementById("applyRecommendedStatus");
const useCategoryAveragePrice = document.getElementById("useCategoryAveragePrice");
const setRestockTarget = document.getElementById("setRestockTarget");
const stockAdvice = document.getElementById("stockAdvice");
let recommendedStatus = "inactive";

const checks = {
    name: document.getElementById("checkName"),
    category: document.getElementById("checkCategory"),
    price: document.getElementById("checkPrice"),
    stock: document.getElementById("checkStock"),
    description: document.getElementById("checkDescription"),
    image: document.getElementById("checkImage"),
};

function setCheck(element, isReady) {
    if (element) {
        element.classList.toggle("is-ready", isReady);
    }
}

function updateReadiness() {
    const name = productNameInput.value.trim();
    const selectedCategory = categoryInput.value !== "";
    const price = Number(priceInput.value);
    const stock = Number(stockInput.value);
    const description = descriptionInput.value.trim();
    const imageName = imageInput.value.trim();
    const hasSelectedImage = productImageInput && productImageInput.files.length > 0;
    const usesPlaceholder = !hasSelectedImage && (imageName === "" || imageName.toLowerCase() === "placeholder.jpg");

    const states = [
        name.length >= 3,
        selectedCategory,
        Number.isFinite(price) && price > 0,
        stockInput.value.trim() !== "" && Number.isInteger(stock) && stock > 0,
        description.length >= 30,
        !usesPlaceholder,
    ];

    setCheck(checks.name, states[0]);
    setCheck(checks.category, states[1]);
    setCheck(checks.price, states[2]);
    setCheck(checks.stock, states[3]);
    setCheck(checks.description, states[4]);
    setCheck(checks.image, states[5]);

    const score = Math.round((states.filter(Boolean).length / states.length) * 100);
    scoreText.textContent = `${score}%`;
    scoreBar.style.width = `${score}%`;
    descriptionCount.textContent = description.length;
    duplicateNotice.hidden = !existingProductNames.includes(name.toLowerCase());

    const hasValidInventoryValue = Number.isFinite(price) && price > 0 && Number.isInteger(stock) && stock >= 0 && stockInput.value.trim() !== "";
    const formattedValue = hasValidInventoryValue ? moneyFormatter.format(price * stock) : "-";
    openingStockValue.textContent = formattedValue;
    summaryInventoryValue.textContent = formattedValue;
    imageReadiness.textContent = usesPlaceholder ? "Placeholder" : "Custom";

    if (stock === 0) {
        stockAdvice.textContent = "Set status to inactive when stock reaches zero.";
    } else if (stock <= 10) {
        stockAdvice.textContent = "Low stock: consider restocking before keeping this active.";
    } else {
        stockAdvice.textContent = "Stock level looks comfortable for active selling.";
    }

    recommendedStatus = score >= 84 && stock > 0 ? "active" : "inactive";
    launchStatusAdvice.textContent = recommendedStatus === "active"
        ? "This product is ready to stay active."
        : "Inactive is recommended until stock, image, and buyer details are stronger.";
}

function updateCategoryIntel() {
    const insight = categoryInsights[categoryInput.value];
    const price = Number(priceInput.value);

    document.getElementById("selectedCategoryName").textContent = insight ? insight.name : "Select a category";
    document.getElementById("categoryProductCount").textContent = insight ? insight.product_count : "-";
    document.getElementById("categoryAveragePrice").textContent = insight ? moneyFormatter.format(insight.average_price) : "-";
    document.getElementById("categoryPriceRange").textContent = insight && insight.max_price > 0 ? `${moneyFormatter.format(insight.min_price)} - ${moneyFormatter.format(insight.max_price)}` : "-";
    document.getElementById("categoryLowStock").textContent = insight ? insight.low_stock_count : "-";

    const guidance = document.getElementById("priceGuidance");
    if (!insight || !Number.isFinite(price) || price <= 0 || insight.average_price <= 0) {
        guidance.textContent = "Choose a category to compare this product price with your catalog.";
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

[productNameInput, categoryInput, priceInput, stockInput, imageInput, productImageInput, descriptionInput].forEach((input) => {
    if (!input) {
        return;
    }

    input.addEventListener("input", () => {
        updateReadiness();
        updateCategoryIntel();
    });
    input.addEventListener("change", () => {
        updateReadiness();
        updateCategoryIntel();
    });
});

function updateProductImagePreview() {
    const file = productImageInput && productImageInput.files.length > 0 ? productImageInput.files[0] : null;

    if (!file) {
        return;
    }

    productImageFileName.textContent = file.name;
    productImagePreview.classList.add("has-image");
    productImagePreviewImg.src = URL.createObjectURL(file);
    productImagePreviewImg.hidden = false;
}

if (productImageInput) {
    productImageInput.addEventListener("change", updateProductImagePreview);
}

document.querySelectorAll("[data-stock-adjust]").forEach((button) => {
    button.addEventListener("click", () => {
        const adjustment = Number(button.dataset.stockAdjust);
        const currentStock = Number(stockInput.value) || 0;
        stockInput.value = Math.max(0, currentStock + adjustment);
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

if (setRestockTarget) {
    setRestockTarget.addEventListener("click", () => {
        stockInput.value = "30";
        updateReadiness();
        updateCategoryIntel();
    });
}

updateReadiness();
updateCategoryIntel();
</script>

<?php require __DIR__ . "/footer.php"; ?>
