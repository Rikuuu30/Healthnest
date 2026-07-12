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
$activeCatalogPercent = $totalProducts > 0 ? round(($activeProducts / $totalProducts) * 100) : 0;
$inventoryValueResult = mysqli_query($conn, "SELECT COALESCE(SUM(price * stock_quantity), 0) AS total FROM products");
$inventoryValueRow = mysqli_fetch_assoc($inventoryValueResult);
$inventoryValue = (float) ($inventoryValueRow["total"] ?? 0);
$lowStockCountResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM products WHERE stock_quantity <= 10");
$lowStockCountRow = mysqli_fetch_assoc($lowStockCountResult);
$lowStockCount = (int) ($lowStockCountRow["total"] ?? 0);
$stockRiskPercent = $totalProducts > 0 ? round(($lowStockCount / $totalProducts) * 100) : 0;

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
    <div class="seller-hero">
        <div>
            <div class="eyebrow">Seller Workspace</div>
            <h1>Command Center</h1>
            <p class="lead">Monitor product health, revenue, and account activity from one polished HealthNest workspace.</p>
        </div>
        <div class="seller-hero-panel">
            <span class="panel-label">Estimated Revenue</span>
            <strong><?php echo formatPrice($totalRevenue); ?></strong>
            <p><?php echo $activeProducts; ?> active products &middot; <?php echo $lowStockCount; ?> need attention</p>
        </div>
    </div>

    <div class="seller-command-strip">
        <div class="command-metric">
            <span>Catalog Readiness</span>
            <strong><?php echo $activeCatalogPercent; ?>%</strong>
            <div class="meter"><i style="width: <?php echo $activeCatalogPercent; ?>%;"></i></div>
        </div>
        <div class="command-metric">
            <span>Stock Risk</span>
            <strong><?php echo $stockRiskPercent; ?>%</strong>
            <div class="meter risk"><i style="width: <?php echo $stockRiskPercent; ?>%;"></i></div>
        </div>
        <div class="command-metric">
            <span>Operational Focus</span>
            <strong><?php echo $lowStockCount > 0 ? "Restock" : "Stable"; ?></strong>
            <p><?php echo $lowStockCount > 0 ? "Prioritize low-stock items today." : "Inventory is currently in a healthy state."; ?></p>
        </div>
    </div>

    <div class="stats-grid">
        <div class="card stat-card">
            <span class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 8 12 4l8.5 4-8.5 4-8.5-4Z"></path><path d="M3.5 8v8L12 20l8.5-4V8"></path><path d="M12 12v8"></path></svg></span>
            <p class="stat-label">Total Products</p>
            <div class="stat-value"><?php echo $totalProducts; ?></div>
            <p class="muted"><?php echo $lowStockCount; ?> low-stock item(s)</p>
            <a href="inventory.php">View inventory</a>
        </div>

        <div class="card stat-card">
            <span class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8.5" r="3.25"></circle><path d="M3.5 19c0-3.2 2.6-5.5 5.5-5.5s5.5 2.3 5.5 5.5"></path><path d="M15.5 6.2c1.4.3 2.5 1.6 2.5 3.1s-1.1 2.8-2.5 3.1"></path><path d="M17 13.6c2 .5 3.5 2.4 3.5 4.6"></path></svg></span>
            <p class="stat-label">Registered Users</p>
            <div class="stat-value"><?php echo $totalUsers; ?></div>
            <p class="muted">Buyer and seller accounts</p>
            <a href="manageusers.php">Manage users</a>
        </div>

        <div class="card stat-card">
            <span class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h2l1.8 10.6a2 2 0 0 0 2 1.7h7a2 2 0 0 0 2-1.6L20 9H6.3"></path><circle cx="9.5" cy="20" r="1.2"></circle><circle cx="17" cy="20" r="1.2"></circle></svg></span>
            <p class="stat-label">Orders</p>
            <div class="stat-value"><?php echo $totalOrders; ?></div>
            <p class="muted">Simulated checkout records</p>
            <a href="auditlog.php">Review activity</a>
        </div>

        <div class="card stat-card">
            <span class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3.5" width="14" height="17" rx="2"></rect><path d="M9 3.5v2.5h6V3.5"></path><path d="M8.5 11.5h7M8.5 15h7M8.5 8.5h3.5"></path></svg></span>
            <p class="stat-label">Audit Logs</p>
            <div class="stat-value"><?php echo $totalLogs; ?></div>
            <p class="muted">Tracked seller actions</p>
            <a href="auditlog.php">View audit log</a>
        </div>
    </div>

    <div class="analytics-grid">
        <div class="card insight-card">
            <span class="panel-label">Inventory Value</span>
            <strong><?php echo formatPrice($inventoryValue); ?></strong>
            <p>Estimated catalog value based on current stock and product prices.</p>
        </div>

        <div class="card insight-card">
            <span class="panel-label">Active Catalog</span>
            <strong><?php echo $activeProducts; ?> / <?php echo $totalProducts; ?></strong>
            <p>Products currently visible to buyers.</p>
        </div>

        <div class="card insight-card warning">
            <span class="panel-label">Stock Watch</span>
            <strong><?php echo $lowStockCount; ?></strong>
            <p>Items at or below the low-stock threshold.</p>
        </div>
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
