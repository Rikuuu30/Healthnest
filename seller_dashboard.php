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
$catalogScore = min(100, max(0, $activeCatalogPercent));
$stockScore = min(100, max(0, 100 - $stockRiskPercent));
$orderScore = $pendingOrders > 0 ? 82 : 100;
$operationsScore = round(($catalogScore + $stockScore + $orderScore) / 3);
$operationsLabel = $operationsScore >= 90 ? "Excellent" : ($operationsScore >= 75 ? "Healthy" : ($operationsScore >= 55 ? "Needs Focus" : "Critical"));

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

$categoryStatsResult = mysqli_query($conn, "
    SELECT c.category_name, COUNT(p.product_id) AS total
    FROM categories c
    LEFT JOIN products p ON c.category_id = p.category_id
    GROUP BY c.category_id, c.category_name
    ORDER BY total DESC, c.category_name ASC
    LIMIT 5
");
$categoryStats = [];
$maxCategoryTotal = 1;
if ($categoryStatsResult) {
    while ($categoryRow = mysqli_fetch_assoc($categoryStatsResult)) {
        $categoryStats[] = $categoryRow;
        $maxCategoryTotal = max($maxCategoryTotal, (int) $categoryRow["total"]);
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
            </div>
        </div>
        <div class="seller-health-card">
            <span class="panel-label">Operations Health</span>
            <div class="health-ring" style="--score: <?php echo $operationsScore; ?>;">
                <strong><?php echo $operationsScore; ?>%</strong>
            </div>
            <h3><?php echo e($operationsLabel); ?></h3>
            <p><?php echo $activeProducts; ?> active products &middot; <?php echo $pendingOrders; ?> pending order(s)</p>
        </div>
    </div>

    <div class="seller-command-strip">
        <div class="command-metric">
            <span>Inventory Value</span>
            <strong><?php echo formatPrice($inventoryValue); ?></strong>
            <p><?php echo $inventoryUnits; ?> units &middot; <?php echo formatPrice($inventoryValuePerUnit); ?> avg/unit</p>
        </div>
        <div class="command-metric">
            <span>Active Catalog</span>
            <strong><?php echo $activeCatalogPercent; ?>%</strong>
            <div class="meter"><i style="width: <?php echo $activeCatalogPercent; ?>%;"></i></div>
        </div>
        <div class="command-metric">
            <span>Stock Risk</span>
            <strong><?php echo $stockRiskPercent; ?>%</strong>
            <div class="meter risk"><i style="width: <?php echo $stockRiskPercent; ?>%;"></i></div>
        </div>
        <div class="command-metric">
            <span>Next Action</span>
            <strong><?php echo $lowStockCount > 0 ? "Restock" : ($inactiveProducts > 0 ? "Review" : "Stable"); ?></strong>
            <p><?php echo $lowStockCount > 0 ? $lowStockCount . " product(s) need stock attention." : ($inactiveProducts > 0 ? $inactiveProducts . " inactive product(s) can be reviewed." : "Catalog is ready for buyers."); ?></p>
        </div>
    </div>

    <div class="seller-analysis-grid">
        <section class="card inventory-value-card">
            <span class="panel-label">Inventory & Status</span>
            <div class="inventory-value-main">
                <strong><?php echo formatPrice($inventoryValue); ?></strong>
                <p>Total stock value from <?php echo $inventoryUnits; ?> available unit(s).</p>
            </div>
            <div class="inventory-status-row">
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
            </div>
            <div class="status-meter">
                <span style="width: <?php echo $activeCatalogPercent; ?>%;"></span>
            </div>
            <p class="muted"><?php echo $activeCatalogPercent; ?>% of catalog is active and visible to buyers.</p>
        </section>

        <section class="card seller-priority-card">
            <span class="panel-label">Seller Priorities</span>
            <ul class="priority-list">
                <li>
                    <strong><?php echo $lowStockCount > 0 ? "Restock low inventory" : "Inventory is healthy"; ?></strong>
                    <span><?php echo $lowStockCount > 0 ? $lowStockCount . " item(s) are at or below 10 units." : "No urgent low-stock products right now."; ?></span>
                </li>
                <li>
                    <strong><?php echo $inactiveProducts > 0 ? "Review inactive products" : "Catalog visibility is strong"; ?></strong>
                    <span><?php echo $inactiveProducts > 0 ? $inactiveProducts . " product(s) are not visible to buyers." : "All products are currently active."; ?></span>
                </li>
                <li>
                    <strong><?php echo $pendingOrders > 0 ? "Check pending orders" : "Orders are clear"; ?></strong>
                    <span><?php echo $pendingOrders > 0 ? $pendingOrders . " order(s) may need follow-up." : "No pending order action detected."; ?></span>
                </li>
            </ul>
        </section>
    </div>

    <div class="dashboard-grid seller-dashboard-grid">
        <div class="table-card">
            <h3>Low Stock Products</h3>
            <p>Products with 10 or fewer items left in stock, ordered by urgency.</p>

            <div class="table-wrap">
                <table border="1" cellpadding="8" cellspacing="0">
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock Level</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    <?php if ($lowStockResult && mysqli_num_rows($lowStockResult) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($lowStockResult)): ?>
                            <?php $stockPercent = max(6, min(100, round(((int) $row["stock_quantity"] / 10) * 100))); ?>
                            <tr>
                                <td><strong><?php echo e($row["product_name"]); ?></strong></td>
                                <td><?php echo e($row["category_name"] ?? "Uncategorized"); ?></td>
                                <td><?php echo formatPrice($row["price"]); ?></td>
                                <td>
                                    <span class="stock-badge stock-low"><?php echo (int) $row["stock_quantity"]; ?> left</span>
                                    <div class="mini-meter"><i style="width: <?php echo $stockPercent; ?>%;"></i></div>
                                </td>
                                <td><span class="status <?php echo e(strtolower($row["status"])); ?>"><?php echo e(ucfirst($row["status"])); ?></span></td>
                                <td><a href="inventory.php?product_id=<?php echo (int) $row["product_id"]; ?>">Restock</a></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">No low stock products. Inventory is healthy.</td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <div class="content-grid">
            <div class="card chart-card revenue-card">
                <h3>Revenue Pulse</h3>
                <p class="muted">Monthly order value from recent checkout records.</p>
                <div class="revenue-bars">
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

            <div class="card chart-card">
                <h3>Category Mix</h3>
                <p class="muted">Product count by leading category.</p>
                <div class="bar-list">
                    <?php if (count($categoryStats) > 0): ?>
                        <?php foreach ($categoryStats as $categoryStat): ?>
                            <?php $barWidth = round(((int) $categoryStat["total"] / $maxCategoryTotal) * 100); ?>
                            <div class="bar-row">
                                <span><?php echo e($categoryStat["category_name"]); ?></span>
                                <strong><?php echo (int) $categoryStat["total"]; ?></strong>
                                <div class="bar-track"><i style="width: <?php echo $barWidth; ?>%;"></i></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="muted">No category data yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card chart-card">
                <h3>Highest Value Products</h3>
                <p class="muted">Ranked by price &times; stock on hand.</p>
                <div class="bar-list">
                    <?php if (count($topProducts) > 0): ?>
                        <?php foreach ($topProducts as $productStat): ?>
                            <?php $barWidth = round(($productStat["value"] / $maxProductValue) * 100); ?>
                            <div class="bar-row">
                                <span><?php echo e($productStat["product_name"]); ?></span>
                                <strong><?php echo formatPrice($productStat["value"]); ?></strong>
                                <div class="bar-track"><i style="width: <?php echo $barWidth; ?>%;"></i></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="muted">No product value data yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <h3>Recent Activity</h3>
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
            </div>
        </div>
    </div>
</main>

<?php require __DIR__ . "/footer.php"; ?>
