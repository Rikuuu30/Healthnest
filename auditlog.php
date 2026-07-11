<?php

require_once __DIR__ . "/init.php";

requireAdmin();

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
    <div class="admin-bar">
        <div>
            <div class="eyebrow">Seller Tools</div>
            <h2>Audit Log</h2>
            <p>Review recorded seller and system actions from the audit table.</p>
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
