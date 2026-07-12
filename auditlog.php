<?php

require_once __DIR__ . "/init.php";

requireAdmin();

$auditStatsResult = mysqli_query($conn, "
    SELECT
        COUNT(*) AS total_logs,
        COUNT(DISTINCT user_id) AS active_actors,
        COUNT(DISTINCT table_affected) AS affected_tables
    FROM audit_logs
");
$auditStats = mysqli_fetch_assoc($auditStatsResult);
$totalLogs = (int) ($auditStats["total_logs"] ?? 0);
$activeActors = (int) ($auditStats["active_actors"] ?? 0);
$affectedTables = (int) ($auditStats["affected_tables"] ?? 0);
$auditTableStatsResult = mysqli_query($conn, "
    SELECT table_affected, COUNT(*) AS total
    FROM audit_logs
    GROUP BY table_affected
    ORDER BY total DESC
    LIMIT 5
");
$auditTableStats = [];
if ($auditTableStatsResult) {
    while ($tableStat = mysqli_fetch_assoc($auditTableStatsResult)) {
        $auditTableStats[] = $tableStat;
    }
}
$result = mysqli_query($conn, "
    SELECT l.log_id, l.action, l.table_affected, l.record_id, l.details, l.created_at,
           a.firstname, a.middlename, a.lastname, a.email
    FROM audit_logs l
    LEFT JOIN tblaccount a ON l.user_id = a.id
    ORDER BY l.log_id DESC
");

$pageTitle = "Audit Log";
require __DIR__ . "/header.php";
?>

<main class="page-main">
    <div class="seller-page-header">
        <div>
            <div class="eyebrow">Seller Tools</div>
            <h2>Audit Log</h2>
            <p>Review recorded seller and system actions from the audit table.</p>
        </div>
    </div>

    <div class="analytics-grid">
        <div class="card insight-card">
            <span class="panel-label">Total Events</span>
            <strong><?php echo $totalLogs; ?></strong>
            <p>Recorded actions in the system trail.</p>
        </div>
        <div class="card insight-card">
            <span class="panel-label">Actors</span>
            <strong><?php echo $activeActors; ?></strong>
            <p>Users connected to audit activity.</p>
        </div>
        <div class="card insight-card">
            <span class="panel-label">Affected Tables</span>
            <strong><?php echo $affectedTables; ?></strong>
            <p>Database areas touched by actions.</p>
        </div>
    </div>

    <div class="card chart-card audit-summary-card">
        <h3>Most Active Tables</h3>
        <div class="status-stack">
            <?php if (count($auditTableStats) > 0): ?>
                <?php foreach ($auditTableStats as $tableStat): ?>
                    <div>
                        <span class="badge"><?php echo e($tableStat["table_affected"] ?: "Unknown"); ?></span>
                        <strong><?php echo (int) $tableStat["total"]; ?></strong>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="muted">No audit activity yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="table-card">
        <h3>Activity Records</h3>
        <div class="table-wrap">
            <table border="1" cellpadding="8" cellspacing="0">
                <tr>
                    <th>Log ID</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Table</th>
                    <th>Record</th>
                    <th>Details</th>
                    <th>Date</th>
                </tr>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?php echo (int) $row["log_id"]; ?></td>
                            <td><?php echo e(accountFullName($row)); ?></td>
                            <td><strong><?php echo e($row["action"]); ?></strong></td>
                            <td><?php echo e($row["table_affected"]); ?></td>
                            <td><?php echo e($row["record_id"]); ?></td>
                            <td><?php echo e($row["details"]); ?></td>
                            <td><?php echo e($row["created_at"]); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">No audit logs found.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</main>

<?php require __DIR__ . "/footer.php"; ?>
