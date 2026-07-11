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
$lowStockCountResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM products WHERE stock_quantity <= 10");
$lowStockCountRow = mysqli_fetch_assoc($lowStockCountResult);
$lowStockCount = (int) ($lowStockCountRow["total"] ?? 0);

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
$recentOrdersResult = mysqli_query($conn, "
    SELECT o.order_id, o.total_amount, o.payment_method, o.status, o.created_at,
           a.firstname, a.middlename, a.lastname, a.email
    FROM orders o
    LEFT JOIN tblaccount a ON o.user_id = a.id
    ORDER BY o.order_id DESC
    LIMIT 5
");

$pageTitle = "Seller Dashboard";
require __DIR__ . "/header.php";
?>

<main class="page-main">
    <div class="admin-bar">
        <div>
            <div class="eyebrow">Seller Workspace</div>
            <h2>Seller Dashboard</h2>
            <p>Monitor products, stock, users, orders, and audit activity from the HealthNest database.</p>
        </div>
    </div>

    <div class="stats-grid">
        <div class="card stat-card">
            <p class="stat-label">Total Products</p>
            <div class="stat-value"><?php echo $totalProducts; ?></div>
            <p class="muted"><?php echo $lowStockCount; ?> low-stock item(s)</p>
            <a href="inventory.php">View inventory</a>
        </div>

        <div class="card stat-card">
            <p class="stat-label">Registered Users</p>
            <div class="stat-value"><?php echo $totalUsers; ?></div>
            <p class="muted">Buyer and seller accounts</p>
            <a href="manageusers.php">Manage users</a>
        </div>

        <div class="card stat-card">
            <p class="stat-label">Orders</p>
            <div class="stat-value"><?php echo $totalOrders; ?></div>
            <p class="muted">Simulated checkout records</p>
            <a href="auditlog.php">Review activity</a>
        </div>

        <div class="card stat-card">
            <p class="stat-label">Audit Logs</p>
            <div class="stat-value"><?php echo $totalLogs; ?></div>
            <p class="muted">Tracked seller actions</p>
            <a href="auditlog.php">View audit log</a>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="table-card">
            <h3>Low Stock Products</h3>
            <p>Products with 10 or fewer items left in stock.</p>

            <div class="table-wrap">
                <table border="1" cellpadding="8" cellspacing="0">
                    <tr>
                        <th>ID</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                    </tr>
                    <?php if ($lowStockResult && mysqli_num_rows($lowStockResult) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($lowStockResult)): ?>
                            <tr>
                                <td><?php echo (int) $row["product_id"]; ?></td>
                                <td><strong><?php echo e($row["product_name"]); ?></strong></td>
                                <td><?php echo e($row["category_name"] ?? "Uncategorized"); ?></td>
                                <td><?php echo formatPrice($row["price"]); ?></td>
                                <td><span class="stock-badge stock-low"><?php echo (int) $row["stock_quantity"]; ?> left</span></td>
                                <td><span class="status <?php echo e(strtolower($row["status"])); ?>"><?php echo e(ucfirst($row["status"])); ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">No low stock products.</td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <div class="content-grid">
            <div class="card">
                <h3>Recent Activity</h3>
                <?php if ($recentLogsResult && mysqli_num_rows($recentLogsResult) > 0): ?>
                    <ul class="activity-list">
                        <?php while ($log = mysqli_fetch_assoc($recentLogsResult)): ?>
                            <li>
                                <strong><?php echo e($log["action"]); ?></strong>
                                <p class="muted"><?php echo e($log["details"]); ?></p>
                                <p class="meta"><?php echo e(accountFullName($log)); ?> - <?php echo e($log["created_at"]); ?></p>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p class="muted">No audit activity yet.</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3>Recent Orders</h3>
                <?php if ($recentOrdersResult && mysqli_num_rows($recentOrdersResult) > 0): ?>
                    <ul class="activity-list">
                        <?php while ($order = mysqli_fetch_assoc($recentOrdersResult)): ?>
                            <li>
                                <strong>Order #<?php echo (int) $order["order_id"]; ?></strong>
                                <p class="muted"><?php echo e(accountFullName($order)); ?> - <?php echo formatPrice($order["total_amount"]); ?></p>
                                <p class="meta"><?php echo e($order["payment_method"]); ?> - <?php echo e(ucfirst($order["status"])); ?></p>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p class="muted">No orders have been placed yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require __DIR__ . "/footer.php"; ?>
