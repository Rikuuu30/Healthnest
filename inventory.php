<?php

require_once __DIR__ . "/init.php";

requireAdmin();

$search = trim($_GET["search"] ?? "");
$categoryFilter = filter_input(INPUT_GET, "category", FILTER_VALIDATE_INT) ?: 0;
$statusFilter = strtolower(trim($_GET["status"] ?? ""));
$stockFilter = strtolower(trim($_GET["stock"] ?? ""));
$sort = strtolower(trim($_GET["sort"] ?? "newest"));
$page = max(1, (int) ($_GET["page"] ?? 1));
$perPage = 10;

if (!in_array($statusFilter, ["active", "inactive"], true)) {
    $statusFilter = "";
}
if (!in_array($stockFilter, ["low", "out"], true)) {
    $stockFilter = "";
}

$sortOptions = [
    "newest" => "p.product_id DESC",
    "oldest" => "p.product_id ASC",
    "name_asc" => "p.product_name ASC",
    "name_desc" => "p.product_name DESC",
    "price_asc" => "p.price ASC",
    "price_desc" => "p.price DESC",
    "stock_asc" => "p.stock_quantity ASC",
    "stock_desc" => "p.stock_quantity DESC",
];
if (!isset($sortOptions[$sort])) {
    $sort = "newest";
}

$inventoryStatsResult = mysqli_query($conn, "
    SELECT
        COUNT(*) AS total_items,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_items,
        SUM(CASE WHEN stock_quantity <= 10 AND stock_quantity > 0 THEN 1 ELSE 0 END) AS low_stock_items,
        SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) AS out_of_stock_items,
        COALESCE(SUM(price * stock_quantity), 0) AS inventory_value
    FROM products
");
$inventoryStats = mysqli_fetch_assoc($inventoryStatsResult);
$totalItems = (int) ($inventoryStats["total_items"] ?? 0);
$activeItems = (int) ($inventoryStats["active_items"] ?? 0);
$lowStockItems = (int) ($inventoryStats["low_stock_items"] ?? 0);
$outOfStockItems = (int) ($inventoryStats["out_of_stock_items"] ?? 0);
$inventoryValue = (float) ($inventoryStats["inventory_value"] ?? 0);

$categories = function_exists("getCategories") ? getCategories($conn) : [];

$conditions = [];
$params = [];
$types = "";

if ($search !== "") {
    $like = "%" . $search . "%";
    $conditions[] = "(p.product_name LIKE ? OR c.category_name LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}
if ($categoryFilter > 0) {
    $conditions[] = "p.category_id = ?";
    $params[] = $categoryFilter;
    $types .= "i";
}
if ($statusFilter !== "") {
    $conditions[] = "p.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}
if ($stockFilter === "low") {
    $conditions[] = "p.stock_quantity <= 10 AND p.stock_quantity > 0";
} elseif ($stockFilter === "out") {
    $conditions[] = "p.stock_quantity = 0";
}

$whereSql = count($conditions) > 0 ? "WHERE " . implode(" AND ", $conditions) : "";

$countSql = "
    SELECT COUNT(*) AS total
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    $whereSql
";
if (count($params) > 0) {
    $countStmt = mysqli_prepare($conn, $countSql);
    mysqli_stmt_bind_param($countStmt, $types, ...$params);
    mysqli_stmt_execute($countStmt);
    $matchingTotal = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))["total"] ?? 0);
} else {
    $matchingTotal = (int) (mysqli_fetch_assoc(mysqli_query($conn, $countSql))["total"] ?? 0);
}

$totalPages = max(1, (int) ceil($matchingTotal / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listSql = "
    SELECT p.product_id, p.product_name, p.description, p.price, p.stock_quantity, p.status, p.image, c.category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    $whereSql
    ORDER BY {$sortOptions[$sort]}
    LIMIT ? OFFSET ?
";
$listParams = $params;
$listParams[] = $perPage;
$listParams[] = $offset;
$listTypes = $types . "ii";
$stmt = mysqli_prepare($conn, $listSql);
mysqli_stmt_bind_param($stmt, $listTypes, ...$listParams);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$rowsForExport = [];
$exportSql = "
    SELECT p.product_id, p.product_name, c.category_name, p.price, p.stock_quantity, p.status
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    $whereSql
    ORDER BY {$sortOptions[$sort]}
";
if (count($params) > 0) {
    $exportStmt = mysqli_prepare($conn, $exportSql);
    mysqli_stmt_bind_param($exportStmt, $types, ...$params);
    mysqli_stmt_execute($exportStmt);
    $exportResult = mysqli_stmt_get_result($exportStmt);
} else {
    $exportResult = mysqli_query($conn, $exportSql);
}
while ($exportRow = mysqli_fetch_assoc($exportResult)) {
    $rowsForExport[] = $exportRow;
}

function inventoryQueryString(array $overrides = [], array $current = []): string
{
    $merged = array_merge($current, $overrides);
    $merged = array_filter($merged, static fn($value) => $value !== "" && $value !== null && $value !== 0);
    return http_build_query($merged);
}

$currentParams = [
    "search" => $search,
    "category" => $categoryFilter ?: "",
    "status" => $statusFilter,
    "stock" => $stockFilter,
    "sort" => $sort,
];

function sortLink(string $ascKey, string $descKey, string $currentSort, array $currentParams, string $label): string
{
    $nextSort = $currentSort === $ascKey ? $descKey : $ascKey;
    $isActive = in_array($currentSort, [$ascKey, $descKey], true);
    $arrow = $currentSort === $descKey ? "&darr;" : "&uarr;";
    $qs = inventoryQueryString(["sort" => $nextSort, "page" => 1], $currentParams);
    $activeClass = $isActive ? "active" : "";
    return "<th class=\"sortable-th $activeClass\"><a href=\"inventory.php?$qs\">$label" . ($isActive ? " <span class=\"arrow\">$arrow</span>" : "") . "</a></th>";
}

$pageTitle = "Inventory";
require __DIR__ . "/header.php";
?>

<main class="page-main">
    <div class="seller-page-header">
        <div>
            <div class="eyebrow">Seller Tools</div>
            <h2>Product Inventory</h2>
            <p>Search, filter, and update the products listed in the HealthNest catalog.</p>
        </div>
        <div class="filter-actions">
            <button type="button" id="exportCsvBtn" class="button secondary">Export CSV</button>
            <a class="button" href="addproduct.php">Add Product</a>
        </div>
    </div>

    <div class="analytics-grid">
        <div class="card insight-card">
            <span class="panel-label">Total Items</span>
            <strong><?php echo $totalItems; ?></strong>
            <p>Products currently stored in the catalog.</p>
        </div>
        <div class="card insight-card">
            <span class="panel-label">Active Items</span>
            <strong><?php echo $activeItems; ?></strong>
            <p>Visible and available for buyers.</p>
        </div>
        <div class="card insight-card warning">
            <span class="panel-label">Low Stock</span>
            <strong><?php echo $lowStockItems; ?></strong>
            <p>Products with 1-10 units remaining.</p>
        </div>
        <div class="card insight-card warning">
            <span class="panel-label">Out of Stock</span>
            <strong><?php echo $outOfStockItems; ?></strong>
            <p>Products with zero units remaining.</p>
        </div>
    </div>

    <form class="filter-bar" method="get" action="inventory.php">
        <div class="field grow">
            <label for="search">Search</label>
            <input id="search" type="text" name="search" placeholder="Product or category name" value="<?php echo e($search); ?>">
        </div>
        <div class="field">
            <label for="category">Category</label>
            <select id="category" name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo (int) $category["category_id"]; ?>" <?php echo $categoryFilter === (int) $category["category_id"] ? "selected" : ""; ?>>
                        <?php echo e($category["category_name"]); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All statuses</option>
                <option value="active" <?php echo $statusFilter === "active" ? "selected" : ""; ?>>Active</option>
                <option value="inactive" <?php echo $statusFilter === "inactive" ? "selected" : ""; ?>>Inactive</option>
            </select>
        </div>
        <div class="field">
            <label for="stock">Stock Level</label>
            <select id="stock" name="stock">
                <option value="">Any stock</option>
                <option value="low" <?php echo $stockFilter === "low" ? "selected" : ""; ?>>Low (&le;10)</option>
                <option value="out" <?php echo $stockFilter === "out" ? "selected" : ""; ?>>Out of stock</option>
            </select>
        </div>
        <input type="hidden" name="sort" value="<?php echo e($sort); ?>">
        <div class="filter-actions">
            <button type="submit">Apply</button>
            <?php if ($search !== "" || $categoryFilter > 0 || $statusFilter !== "" || $stockFilter !== ""): ?>
                <a class="button secondary" href="inventory.php">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="table-card inventory-list-card">
        <div class="catalog-control-shell">
            <div class="table-card-header">
                <div class="catalog-title-block">
                <span class="panel-label">Catalog Control</span>
                <h3>Inventory List</h3>
                <p class="filter-count"><span id="inventoryVisibleCount"><?php echo $matchingTotal; ?></span> of <?php echo $matchingTotal; ?> product<?php echo $matchingTotal === 1 ? "" : "s"; ?> shown.</p>
                </div>
                <div class="table-tools">
                    <div class="table-tools-head">
                        <label for="inventoryPageSearch">Search this page</label>
                        <label class="inline-filter compact-toggle"><input id="inventoryCompactToggle" type="checkbox"> Compact rows</label>
                    </div>
                    <div class="table-search-row">
                        <input id="inventoryPageSearch" type="search" placeholder="Filter visible rows" autocomplete="off">
                        <button id="clearInventoryPageSearch" type="button" class="icon-text-button">Clear</button>
                    </div>
                </div>
            </div>
            <div class="quick-filter-row">
                <a class="<?php echo $stockFilter === "" && $statusFilter === "" ? "active" : ""; ?>" href="inventory.php">All</a>
                <a class="<?php echo $statusFilter === "active" ? "active" : ""; ?>" href="inventory.php?<?php echo inventoryQueryString(["status" => "active", "page" => 1], $currentParams); ?>">Active</a>
                <a class="<?php echo $stockFilter === "low" ? "active" : ""; ?>" href="inventory.php?<?php echo inventoryQueryString(["stock" => "low", "page" => 1], $currentParams); ?>">Low Stock</a>
                <a class="<?php echo $stockFilter === "out" ? "active" : ""; ?>" href="inventory.php?<?php echo inventoryQueryString(["stock" => "out", "page" => 1], $currentParams); ?>">Out of Stock</a>
            </div>
        </div>
        <div class="catalog-table-frame">
        <div class="table-wrap">
            <table class="inventory-table" border="1" cellpadding="8" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <?php echo sortLink("name_asc", "name_desc", $sort, $currentParams, "Product"); ?>
                        <?php echo sortLink("price_asc", "price_desc", $sort, $currentParams, "Price"); ?>
                        <?php echo sortLink("stock_asc", "stock_desc", $sort, $currentParams, "Stock"); ?>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <?php
                        $qty = (int) $row["stock_quantity"];
                        $stockClass = $qty <= 10 ? "stock-low" : "stock-ok";
                        $stockPercent = max(4, min(100, round(($qty / 30) * 100)));
                        $imgSrc = trim((string) ($row["image"] ?? ""));
                        ?>
                        <tr class="inventory-row" data-table-row data-status="<?php echo e(strtolower($row["status"])); ?>" data-stock="<?php echo $qty === 0 ? "out" : ($qty <= 10 ? "low" : "ok"); ?>">
                            <td class="id-cell">#<?php echo (int) $row["product_id"]; ?></td>
                            <td>
                                <div class="product-cell">
                                    <?php if ($imgSrc !== "" && $imgSrc !== "placeholder.jpg"): ?>
                                        <div class="product-thumb-wrap">
                                            <img src="images/<?php echo e($imgSrc); ?>" alt="" onerror="this.parentElement.style.display='none';">
                                        </div>
                                    <?php endif; ?>
                                    <div class="product-cell-copy">
                                        <strong><?php echo e($row["product_name"]); ?></strong>
                                        <span><?php echo e($row["category_name"] ?? "Uncategorized"); ?></span>
                                        <em><?php echo e($row["description"] ?: "No description"); ?></em>
                                    </div>
                                </div>
                            </td>
                            <td class="price-cell"><?php echo formatPrice($row["price"]); ?></td>
                            <td class="stock-cell">
                                <span class="stock-badge <?php echo $stockClass; ?>"><?php echo $qty === 0 ? "Out" : $qty; ?></span>
                                <div class="mini-meter <?php echo $qty <= 10 ? "risk" : ""; ?>"><i style="width: <?php echo $stockPercent; ?>%;"></i></div>
                            </td>
                            <td class="inventory-status-cell"><span class="status <?php echo e(strtolower($row["status"])); ?>"><?php echo e(ucfirst($row["status"])); ?></span></td>
                            <td class="table-action-cell">
                                <a href="editproduct.php?id=<?php echo (int) $row["product_id"]; ?>">Edit</a>
                                <a class="danger-link" data-confirm-delete href="deleteproduct.php?id=<?php echo (int) $row["product_id"]; ?>">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr class="empty-table-row">
                        <td colspan="6">
                            <div class="empty-state">No products match your current filters.</div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
            <p id="inventoryPageEmpty" class="muted table-empty-note" hidden>No visible products match your page search.</p>
        </div>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <span class="page-summary">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                <div class="page-links">
                    <?php if ($page > 1): ?>
                        <a href="inventory.php?<?php echo inventoryQueryString(["page" => $page - 1], $currentParams); ?>">Previous</a>
                    <?php endif; ?>
                    <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                        <?php if ($p === $page): ?>
                            <span class="current"><?php echo $p; ?></span>
                        <?php else: ?>
                            <a href="inventory.php?<?php echo inventoryQueryString(["page" => $p], $currentParams); ?>"><?php echo $p; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="inventory.php?<?php echo inventoryQueryString(["page" => $page + 1], $currentParams); ?>">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
const exportRows = <?php echo json_encode($rowsForExport, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
const exportBtn = document.getElementById("exportCsvBtn");
const inventoryPageSearch = document.getElementById("inventoryPageSearch");
const clearInventoryPageSearch = document.getElementById("clearInventoryPageSearch");
const inventoryCompactToggle = document.getElementById("inventoryCompactToggle");
const inventoryRows = Array.from(document.querySelectorAll(".inventory-row"));
const inventoryVisibleCount = document.getElementById("inventoryVisibleCount");
const inventoryPageEmpty = document.getElementById("inventoryPageEmpty");
const inventoryTable = document.querySelector(".inventory-table");

function applyInventoryPageSearch() {
    const query = inventoryPageSearch ? inventoryPageSearch.value.trim().toLowerCase() : "";
    let visible = 0;

    inventoryRows.forEach((row) => {
        const isVisible = row.textContent.toLowerCase().includes(query);
        row.hidden = !isVisible;
        visible += isVisible ? 1 : 0;
    });

    if (inventoryVisibleCount) {
        inventoryVisibleCount.textContent = visible;
    }
    if (inventoryPageEmpty) {
        inventoryPageEmpty.hidden = visible !== 0;
    }
}

if (inventoryPageSearch) {
    inventoryPageSearch.addEventListener("input", applyInventoryPageSearch);
}

if (clearInventoryPageSearch && inventoryPageSearch) {
    clearInventoryPageSearch.addEventListener("click", () => {
        inventoryPageSearch.value = "";
        applyInventoryPageSearch();
        inventoryPageSearch.focus();
    });
}

if (inventoryCompactToggle && inventoryTable) {
    inventoryCompactToggle.addEventListener("change", () => {
        inventoryTable.classList.toggle("is-compact", inventoryCompactToggle.checked);
    });
}

document.querySelectorAll("[data-confirm-delete]").forEach((link) => {
    link.addEventListener("click", (event) => {
        if (!confirm("Delete this product from inventory?")) {
            event.preventDefault();
        }
    });
});

if (exportBtn) {
    exportBtn.addEventListener("click", () => {
        const header = ["Product ID", "Product Name", "Category", "Price", "Stock", "Status"];
        const lines = [header.join(",")];

        exportRows.forEach((row) => {
            const cells = [
                row.product_id,
                row.product_name,
                row.category_name || "Uncategorized",
                row.price,
                row.stock_quantity,
                row.status,
            ].map((value) => {
                const text = String(value ?? "");
                return /[",\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
            });
            lines.push(cells.join(","));
        });

        const blob = new Blob([lines.join("\n")], { type: "text/csv;charset=utf-8;" });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = "inventory-export.csv";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    });
}

applyInventoryPageSearch();
</script>

<?php require __DIR__ . "/footer.php"; ?>
