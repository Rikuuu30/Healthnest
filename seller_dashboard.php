<?php

require_once __DIR__ . "/init.php";

requireAdmin();

function countRows($conn, $table)
{
    $allowedTables = ["products", "tblaccount", "audit_logs", "orders"];

    if (!in_array($table, $allowedTables, true)) {
        return 0;
    }

    $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM `$table`");
    $row = mysqli_fetch_assoc($result);

    return (int) ($row["total"] ?? 0);
}

$totalProducts = countRows($conn, "products");
$totalUsers = countRows($conn, "tblaccount");
$totalLogs = countRows($conn, "audit_logs");
$totalOrders = countRows($conn, "orders");
$revenueResult = mysqli_query($conn, "SELECT COALESCE(SUM(total_amount), 0) AS total FROM orders");
$revenueRow = mysqli_fetch_assoc($revenueResult);
$totalRevenue = (float) ($revenueRow["total"] ?? 0);
$activeProductResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM products WHERE status = 'active'");
$activeProductRow = mysqli_fetch_assoc($activeProductResult);
$activeProducts = (int) ($activeProductRow["total"] ?? 0);
$inactiveProducts = max(0, $totalProducts - $activeProducts);
$activeCatalogPercent = $totalProducts > 0 ? round(($activeProducts / $totalProducts) * 100) : 0;
$inventoryValueResult = mysqli_query($conn, "SELECT COALESCE(SUM(price * stock_quantity), 0) AS total FROM products");
$inventoryValueRow = mysqli_fetch_assoc($inventoryValueResult);
$inventoryValue = (float) ($inventoryValueRow["total"] ?? 0);
$lowStockCountResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM products WHERE stock_quantity <= 10");
$lowStockCountRow = mysqli_fetch_assoc($lowStockCountResult);
$lowStockCount = (int) ($lowStockCountRow["total"] ?? 0);
$stockRiskPercent = $totalProducts > 0 ? round(($lowStockCount / $totalProducts) * 100) : 0;
$inventoryUnitsResult = mysqli_query($conn, "SELECT COALESCE(SUM(stock_quantity), 0) AS total FROM products");
$inventoryUnitsRow = mysqli_fetch_assoc($inventoryUnitsResult);
$inventoryUnits = (int) ($inventoryUnitsRow["total"] ?? 0);
$pendingOrderResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM orders WHERE status IS NULL OR LOWER(status) NOT IN ('completed', 'paid', 'delivered', 'cancelled')");
$pendingOrderRow = mysqli_fetch_assoc($pendingOrderResult);
$pendingOrders = (int) ($pendingOrderRow["total"] ?? 0);
$todayActivityResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM audit_logs WHERE created_at = CURDATE()");
$todayActivityRow = mysqli_fetch_assoc($todayActivityResult);
$todayActivity = (int) ($todayActivityRow["total"] ?? 0);
$activeUserResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tblaccount WHERE status = 'active'");
$activeUserRow = mysqli_fetch_assoc($activeUserResult);
$activeUsers = (int) ($activeUserRow["total"] ?? 0);
$averageOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
$inventoryValuePerUnit = $inventoryUnits > 0 ? $inventoryValue / $inventoryUnits : 0;
$soldUnitsResult = mysqli_query($conn, "SELECT COALESCE(SUM(quantity), 0) AS total FROM order_items");
$soldUnitsRow = $soldUnitsResult ? mysqli_fetch_assoc($soldUnitsResult) : null;
$soldUnits = (int) ($soldUnitsRow["total"] ?? 0);
$sellThroughPercent = ($soldUnits + $inventoryUnits) > 0 ? round(($soldUnits / ($soldUnits + $inventoryUnits)) * 100) : 0;
$todayRevenueResult = mysqli_query($conn, "SELECT COALESCE(SUM(total_amount), 0) AS total FROM orders WHERE created_at = CURDATE()");
$todayRevenueRow = $todayRevenueResult ? mysqli_fetch_assoc($todayRevenueResult) : null;
$todayRevenue = (float) ($todayRevenueRow["total"] ?? 0);
$todayOrdersResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM orders WHERE created_at = CURDATE()");
$todayOrdersRow = $todayOrdersResult ? mysqli_fetch_assoc($todayOrdersResult) : null;
$todayOrders = (int) ($todayOrdersRow["total"] ?? 0);
$criticalStockCountResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM products WHERE stock_quantity <= 5");
$criticalStockCountRow = $criticalStockCountResult ? mysqli_fetch_assoc($criticalStockCountResult) : null;
$criticalStockCount = (int) ($criticalStockCountRow["total"] ?? 0);
$catalogScore = min(100, max(0, $activeCatalogPercent));
$stockScore = min(100, max(0, 100 - $stockRiskPercent));
$orderScore = $pendingOrders > 0 ? 82 : 100;
$operationsScore = round(($catalogScore + $stockScore + $orderScore) / 3);
$operationsLabel = $operationsScore >= 90 ? "Excellent" : ($operationsScore >= 75 ? "Healthy" : ($operationsScore >= 55 ? "Needs Focus" : "Critical"));
$dashboardPrompt = $criticalStockCount > 0
    ? "Restock " . $criticalStockCount . " critical item(s) first."
    : ($lowStockCount > 0 ? "Review " . $lowStockCount . " low-stock item(s)." : "Catalog stock is currently steady.");

$monthlyRevenueResult = mysqli_query($conn, "
    SELECT DATE_FORMAT(created_at, '%b') AS month_label, COALESCE(SUM(total_amount), 0) AS total
    FROM orders
    WHERE created_at IS NOT NULL
    GROUP BY YEAR(created_at), MONTH(created_at), DATE_FORMAT(created_at, '%b')
    ORDER BY YEAR(created_at) DESC, MONTH(created_at) DESC
    LIMIT 6
");
$monthlyRevenue = [];
$maxMonthlyRevenue = 1;
if ($monthlyRevenueResult) {
    while ($monthRow = mysqli_fetch_assoc($monthlyRevenueResult)) {
        $monthlyRevenue[] = $monthRow;
        $maxMonthlyRevenue = max($maxMonthlyRevenue, (float) $monthRow["total"]);
    }
    $monthlyRevenue = array_reverse($monthlyRevenue);
}

$topProductsResult = mysqli_query($conn, "
    SELECT p.product_id, p.product_name, p.price, p.stock_quantity, c.category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    ORDER BY (p.price * p.stock_quantity) DESC
    LIMIT 5
");
$topProducts = [];
$maxProductValue = 1;
if ($topProductsResult) {
    while ($productRow = mysqli_fetch_assoc($topProductsResult)) {
        $productRow["value"] = (float) $productRow["price"] * (int) $productRow["stock_quantity"];
        $topProducts[] = $productRow;
        $maxProductValue = max($maxProductValue, $productRow["value"]);
    }
}

$topSellingResult = mysqli_query($conn, "
    SELECT p.product_name, c.category_name, COALESCE(SUM(oi.quantity), 0) AS units_sold, COALESCE(SUM(oi.subtotal), 0) AS sales_value
    FROM order_items oi
    INNER JOIN products p ON oi.product_id = p.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    GROUP BY p.product_id, p.product_name, c.category_name
    ORDER BY units_sold DESC, sales_value DESC
    LIMIT 5
");
$topSellingProducts = [];
$maxUnitsSold = 1;
if ($topSellingResult) {
    while ($salesRow = mysqli_fetch_assoc($topSellingResult)) {
        $topSellingProducts[] = $salesRow;
        $maxUnitsSold = max($maxUnitsSold, (int) $salesRow["units_sold"]);
    }
}

$categoryRevenueResult = mysqli_query($conn, "
    SELECT c.category_name, COALESCE(SUM(oi.subtotal), 0) AS sales_value
    FROM order_items oi
    INNER JOIN products p ON oi.product_id = p.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    GROUP BY c.category_id, c.category_name
    ORDER BY sales_value DESC
    LIMIT 4
");
$categoryRevenue = [];
$maxCategoryRevenue = 1;
if ($categoryRevenueResult) {
    while ($categoryRow = mysqli_fetch_assoc($categoryRevenueResult)) {
        $categoryRevenue[] = $categoryRow;
        $maxCategoryRevenue = max($maxCategoryRevenue, (float) $categoryRow["sales_value"]);
    }
}

$lowStockSql = "
    SELECT p.product_id, p.product_name, p.price, p.stock_quantity, p.status, c.category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE p.stock_quantity <= 10
    ORDER BY p.stock_quantity ASC, p.product_name ASC
";
$lowStockResult = mysqli_query($conn, $lowStockSql);
$recentLogsResult = mysqli_query($conn, "
    SELECT l.action, l.table_affected, l.record_id, l.details, l.created_at,
           a.firstname, a.middlename, a.lastname, a.email
    FROM audit_logs l
    LEFT JOIN tblaccount a ON l.user_id = a.id
    ORDER BY l.log_id DESC
    LIMIT 5
");

$pageTitle = "Seller Dashboard";
require __DIR__ . "/header.php";
?>

<main class="page-main">
    <div class="seller-hero seller-dashboard-hero">
        <div>
            <div class="eyebrow">Seller Workspace</div>
            <h1>Command Center</h1>
            <p class="lead">Monitor product health, revenue, and account activity from one polished HealthNest workspace.</p>
            <div class="seller-hero-actions">
                <a class="button" href="addproduct.php">Add Product</a>
                <a class="button secondary" href="inventory.php">Review Inventory</a>
                <a class="button secondary" href="auditlog.php">Audit Activity</a>
            </div>
        </div>
        <div class="seller-health-card">
            <span class="panel-label">Operations Health</span>
            <div class="health-ring" style="--score: <?php echo $operationsScore; ?>;">
                <strong><?php echo $operationsScore; ?>%</strong>
            </div>
            <h3><?php echo e($operationsLabel); ?></h3>
            <p><?php echo e($dashboardPrompt); ?></p>
        </div>
    </div>

    <div class="seller-workbench-grid">
        <div class="seller-main-column">
            <section class="card sales-analytics-card">
                <div class="card-heading-row compact">
                    <div>
                        <span class="panel-label">Sales Interpretation</span>
                        <h3>Sales Performance</h3>
                        <p class="muted">Revenue trend and product movement from checkout records.</p>
                    </div>
                </div>

                <div class="dashboard-stat-strip">
                    <div>
                        <span>Total Revenue</span>
                        <strong><?php echo formatPrice($totalRevenue); ?></strong>
                    </div>
                    <div>
                        <span>Average Order</span>
                        <strong><?php echo formatPrice($averageOrderValue); ?></strong>
                    </div>
                    <div>
                        <span>Today</span>
                        <strong><?php echo formatPrice($todayRevenue); ?></strong>
                    </div>
                    <div>
                        <span>Sell-through</span>
                        <strong><?php echo $sellThroughPercent; ?>%</strong>
                    </div>
                </div>

                <div class="sales-graph-grid">
                    <div class="graph-panel">
                        <div class="graph-heading">
                            <strong>Monthly Revenue</strong>
                            <span><?php echo count($monthlyRevenue); ?> month view</span>
                        </div>
                        <div class="revenue-bars compact-bars">
                            <?php if (count($monthlyRevenue) > 0): ?>
                                <?php foreach ($monthlyRevenue as $monthStat): ?>
                                    <?php $barHeight = max(10, round(((float) $monthStat["total"] / $maxMonthlyRevenue) * 100)); ?>
                                    <div class="revenue-bar">
                                        <span style="height: <?php echo $barHeight; ?>%;"></span>
                                        <strong><?php echo e($monthStat["month_label"]); ?></strong>
                                        <em><?php echo formatPrice((float) $monthStat["total"]); ?></em>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="muted">No dated revenue records yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="graph-panel">
                        <div class="graph-heading">
                            <strong>Top Sellers</strong>
                            <span>Units sold</span>
                        </div>
                        <div class="sales-product-bars">
                            <?php if (count($topSellingProducts) > 0): ?>
                                <?php foreach ($topSellingProducts as $salesProduct): ?>
                                    <?php $barWidth = round(((int) $salesProduct["units_sold"] / $maxUnitsSold) * 100); ?>
                                    <div class="sales-product-row">
                                        <span><?php echo e($salesProduct["product_name"]); ?></span>
                                        <strong><?php echo (int) $salesProduct["units_sold"]; ?> sold</strong>
                                        <div class="bar-track"><i style="width: <?php echo $barWidth; ?>%;"></i></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="muted">Product sales will appear after orders include items.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="graph-panel category-revenue-panel">
                        <div class="graph-heading">
                            <strong>Category Revenue</strong>
                            <span>Sales mix</span>
                        </div>
                        <div class="category-revenue-bars">
                            <?php if (count($categoryRevenue) > 0): ?>
                                <?php foreach ($categoryRevenue as $categoryStat): ?>
                                    <?php $barWidth = round(((float) $categoryStat["sales_value"] / $maxCategoryRevenue) * 100); ?>
                                    <div class="category-revenue-row">
                                        <span><?php echo e($categoryStat["category_name"] ?? "Uncategorized"); ?></span>
                                        <strong><?php echo formatPrice((float) $categoryStat["sales_value"]); ?></strong>
                                        <div class="bar-track"><i style="width: <?php echo $barWidth; ?>%;"></i></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="muted">Category sales will appear after completed checkout items.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <div class="table-card low-stock-card">
                <div class="inventory-watch-header">
                    <div class="inventory-watch-title">
                        <span class="panel-label">Inventory Priorities</span>
                        <h3>Stock Readiness</h3>
                        <p>A dashboard summary of catalog availability, restock urgency, and products that need attention first.</p>
                    </div>
                    <div class="inventory-summary-pills">
                        <div>
                            <span>Active</span>
                            <strong><?php echo $activeProducts; ?></strong>
                        </div>
                        <div>
                            <span>Inactive</span>
                            <strong><?php echo $inactiveProducts; ?></strong>
                        </div>
                        <div class="<?php echo $lowStockCount > 0 ? "needs-attention" : ""; ?>">
                            <span>Low Stock</span>
                            <strong><?php echo $lowStockCount; ?></strong>
                        </div>
                        <div class="<?php echo $criticalStockCount > 0 ? "needs-attention" : ""; ?>">
                            <span>Critical</span>
                            <strong><?php echo $criticalStockCount; ?></strong>
                        </div>
                    </div>
                    <div class="inventory-watch-tools">
                        <div class="dashboard-search">
                            <label for="lowStockSearch">Find Product</label>
                            <input id="lowStockSearch" type="search" placeholder="Search priority items" autocomplete="off">
                        </div>
                        <label class="inline-filter"><input id="criticalOnlyFilter" type="checkbox"> Critical only</label>
                    </div>
                </div>

                <div class="inventory-priority-insights">
                    <div>
                        <span>Availability</span>
                        <strong><?php echo $activeProducts; ?> ready</strong>
                        <p><?php echo $inactiveProducts; ?> inactive listing<?php echo $inactiveProducts === 1 ? "" : "s"; ?> should be reviewed before promotion.</p>
                    </div>
                    <div class="<?php echo $criticalStockCount > 0 ? "needs-attention" : ""; ?>">
                        <span>Restock Focus</span>
                        <strong><?php echo $criticalStockCount > 0 ? $criticalStockCount . " critical" : "Stable"; ?></strong>
                        <p><?php echo $lowStockCount > 0 ? $lowStockCount . " product" . ($lowStockCount === 1 ? "" : "s") . " at or below 10 units." : "No urgent stock issues detected."; ?></p>
                    </div>
                    <div>
                        <span>Next Move</span>
                        <strong><?php echo $lowStockCount > 0 ? "Restock queue" : "Monitor sales"; ?></strong>
                        <p><?php echo $lowStockCount > 0 ? "Prioritize critical products first, then top up watch items." : "Inventory is healthy, so review demand and category performance."; ?></p>
                    </div>
                </div>

                <div class="table-wrap">
                <table id="lowStockTable" class="inventory-watch-table" border="1" cellpadding="8" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Stock Level</th>
                            <th>Restock Plan</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($lowStockResult && mysqli_num_rows($lowStockResult) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($lowStockResult)): ?>
                            <?php $stockPercent = max(6, min(100, round(((int) $row["stock_quantity"] / 10) * 100))); ?>
                            <?php $suggestedRestock = max(0, 30 - (int) $row["stock_quantity"]); ?>
                            <?php $stockSeverity = (int) $row["stock_quantity"] <= 5 ? "critical" : "watch"; ?>
                            <tr data-stock-severity="<?php echo e($stockSeverity); ?>">
                                <td class="watch-product-cell">
                                    <strong><?php echo e($row["product_name"]); ?></strong>
                                    <span><?php echo e($row["category_name"] ?? "Uncategorized"); ?> &middot; <?php echo formatPrice($row["price"]); ?></span>
                                </td>
                                <td class="watch-stock-cell">
                                    <span class="stock-badge <?php echo $stockSeverity === "critical" ? "stock-critical" : "stock-low"; ?>"><?php echo (int) $row["stock_quantity"]; ?> left</span>
                                    <div class="mini-meter"><i style="width: <?php echo $stockPercent; ?>%;"></i></div>
                                </td>
                                <td class="watch-restock-cell"><strong><?php echo $suggestedRestock; ?></strong><span>units to reach 30</span></td>
                                <td class="watch-action-cell"><a href="editproduct.php?id=<?php echo (int) $row["product_id"]; ?>">Restock</a></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr class="inventory-watch-empty-row">
                            <td colspan="4">No low stock products. Inventory is healthy.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <p id="lowStockEmpty" class="muted table-empty-note" hidden>No matching low stock product.</p>
                </div>
            </div>
        </div>

    </div>

    <div class="seller-lower-grid">
        <section class="card seller-insights-card">
            <div class="card-heading-row compact">
                <div>
                    <span class="panel-label">Catalog Signals</span>
                    <h3>Highest Value Products</h3>
                </div>
            </div>
            <p class="muted">Ranked by price &times; stock on hand.</p>
            <div class="bar-list value-product-list">
                <?php if (count($topProducts) > 0): ?>
                    <?php foreach ($topProducts as $productStat): ?>
                        <?php $barWidth = round(($productStat["value"] / $maxProductValue) * 100); ?>
                        <div class="bar-row">
                            <span>
                                <?php echo e($productStat["product_name"]); ?>
                                <em><?php echo e($productStat["category_name"] ?? "Uncategorized"); ?> &middot; <?php echo (int) $productStat["stock_quantity"]; ?> units</em>
                            </span>
                            <strong><?php echo formatPrice($productStat["value"]); ?></strong>
                            <div class="bar-track"><i style="width: <?php echo $barWidth; ?>%;"></i></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="muted">No product value data yet.</p>
                <?php endif; ?>
            </div>
        </section>

        <div class="seller-activity-stack">
            <section class="card recent-activity-card">
                <div class="card-heading-row compact">
                    <div>
                        <span class="panel-label">Audit Trail</span>
                        <h3>Recent Activity</h3>
                    </div>
                </div>
                <?php if ($recentLogsResult && mysqli_num_rows($recentLogsResult) > 0): ?>
                    <ul class="activity-list">
                        <?php while ($log = mysqli_fetch_assoc($recentLogsResult)): ?>
                            <li>
                                <div class="activity-row">
                                    <span class="activity-dot"></span>
                                    <div>
                                        <strong><?php echo e($log["action"]); ?></strong>
                                        <p class="muted"><?php echo e($log["details"]); ?></p>
                                        <p class="meta"><?php echo e(accountFullName($log)); ?> &middot; <?php echo e($log["created_at"]); ?></p>
                                    </div>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p class="muted">No audit activity yet.</p>
                <?php endif; ?>
            </section>

            <section class="card seller-action-summary-card">
                <span class="panel-label">Seller Notes</span>
                <h3>Next Useful Check</h3>
                <p><?php echo e($dashboardPrompt); ?></p>
                <div class="summary-action-row">
                    <a href="inventory.php">Inventory</a>
                    <a href="auditlog.php">Audit Log</a>
                </div>
            </section>
        </div>
    </div>
</main>

<script>
const lowStockSearch = document.getElementById("lowStockSearch");
const lowStockTable = document.getElementById("lowStockTable");
const lowStockEmpty = document.getElementById("lowStockEmpty");
const criticalOnlyFilter = document.getElementById("criticalOnlyFilter");

if (lowStockSearch && lowStockTable && lowStockEmpty) {
    const applyLowStockFilters = () => {
        const query = lowStockSearch.value.trim().toLowerCase();
        const criticalOnly = criticalOnlyFilter ? criticalOnlyFilter.checked : false;
        const rows = Array.from(lowStockTable.querySelectorAll("tr[data-stock-severity]"));
        let visibleRows = 0;

        rows.forEach((row) => {
            const matchesSearch = row.textContent.toLowerCase().includes(query);
            const matchesSeverity = !criticalOnly || row.dataset.stockSeverity === "critical";
            const isMatch = matchesSearch && matchesSeverity;
            row.hidden = !isMatch;
            visibleRows += isMatch ? 1 : 0;
        });

        lowStockEmpty.hidden = visibleRows !== 0;
    };

    lowStockSearch.addEventListener("input", applyLowStockFilters);

    if (criticalOnlyFilter) {
        criticalOnlyFilter.addEventListener("change", applyLowStockFilters);
    }
}
</script>

<?php require __DIR__ . "/footer.php"; ?>
