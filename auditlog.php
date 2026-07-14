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

    <div class="table-card audit-records-card">
        <div class="table-card-header">
            <div>
                <span class="panel-label">Event Stream</span>
                <h3>Activity Records</h3>
                <p class="filter-count"><span id="auditVisibleCount"><?php echo $totalLogs; ?></span> of <?php echo $totalLogs; ?> event<?php echo $totalLogs === 1 ? "" : "s"; ?> shown.</p>
            </div>
            <div class="audit-table-summary">
                <span><?php echo $activeActors; ?> actors</span>
                <span><?php echo $affectedTables; ?> tables</span>
            </div>
        </div>
        <div class="table-tools audit-table-tools">
            <label for="auditPageSearch">Search records</label>
            <div class="table-search-row">
                <input id="auditPageSearch" type="search" placeholder="Filter by action, user, table, target, detail, or date" autocomplete="off">
                <button id="clearAuditSearch" type="button" class="icon-text-button">Clear</button>
                <button id="exportAuditCsv" type="button" class="icon-text-button">Export</button>
            </div>
            <div class="segmented-filters" aria-label="Audit filters">
                <button type="button" class="active" data-audit-filter="all">All</button>
                <button type="button" data-audit-filter="products">Products</button>
                <button type="button" data-audit-filter="tblaccount">Accounts</button>
                <button type="button" data-audit-filter="orders">Orders</button>
            </div>
        </div>
        <div class="table-wrap">
            <table class="audit-table" border="1" cellpadding="8" cellspacing="0">
                <thead>
                    <tr>
                        <th>Log</th>
                        <th>Actor</th>
                        <th>Event</th>
                        <th>Target</th>
                        <th>Details</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <?php $auditTable = strtolower((string) $row["table_affected"]); ?>
                        <tr class="audit-row" data-table-row data-audit-table="<?php echo e($auditTable); ?>">
                            <td class="id-cell">#<?php echo (int) $row["log_id"]; ?></td>
                            <td class="audit-actor-cell">
                                <strong><?php echo e(accountFullName($row)); ?></strong>
                                <span><?php echo e($row["email"] ?: "System activity"); ?></span>
                            </td>
                            <td class="audit-action-cell"><strong><?php echo e($row["action"]); ?></strong></td>
                            <td class="audit-target-cell">
                                <span class="badge"><?php echo e($row["table_affected"] ?: "Unknown"); ?></span>
                                <em>Record #<?php echo e($row["record_id"] ?: "-"); ?></em>
                            </td>
                            <td class="audit-detail-cell"><?php echo e($row["details"]); ?></td>
                            <td class="audit-date-cell"><?php echo e($row["created_at"]); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr class="empty-table-row">
                        <td colspan="6">No audit logs found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
            <p id="auditPageEmpty" class="muted table-empty-note" hidden>No audit records match your current filters.</p>
        </div>
    </div>
</main>

<script>
const auditSearch = document.getElementById("auditPageSearch");
const clearAuditSearch = document.getElementById("clearAuditSearch");
const auditRows = Array.from(document.querySelectorAll(".audit-row"));
const auditVisibleCount = document.getElementById("auditVisibleCount");
const auditEmpty = document.getElementById("auditPageEmpty");
const auditFilterButtons = Array.from(document.querySelectorAll("[data-audit-filter]"));
const exportAuditCsv = document.getElementById("exportAuditCsv");
let activeAuditFilter = "all";

function applyAuditFilters() {
    const query = auditSearch ? auditSearch.value.trim().toLowerCase() : "";
    let visible = 0;

    auditRows.forEach((row) => {
        const tableName = row.dataset.auditTable || "";
        const matchesSearch = row.textContent.toLowerCase().includes(query);
        const matchesFilter = activeAuditFilter === "all"
            || tableName === activeAuditFilter
            || (activeAuditFilter === "orders" && tableName.includes("order"));
        const isVisible = matchesSearch && matchesFilter;
        row.hidden = !isVisible;
        visible += isVisible ? 1 : 0;
    });

    if (auditVisibleCount) {
        auditVisibleCount.textContent = visible;
    }
    if (auditEmpty) {
        auditEmpty.hidden = visible !== 0;
    }
}

if (auditSearch) {
    auditSearch.addEventListener("input", applyAuditFilters);
}

if (clearAuditSearch && auditSearch) {
    clearAuditSearch.addEventListener("click", () => {
        auditSearch.value = "";
        applyAuditFilters();
        auditSearch.focus();
    });
}

auditFilterButtons.forEach((button) => {
    button.addEventListener("click", () => {
        activeAuditFilter = button.dataset.auditFilter;
        auditFilterButtons.forEach((item) => item.classList.toggle("active", item === button));
        applyAuditFilters();
    });
});

if (exportAuditCsv) {
    exportAuditCsv.addEventListener("click", () => {
        const visibleRows = auditRows.filter((row) => !row.hidden);
        const lines = [["Log", "Actor", "Event", "Target", "Details", "Date"].join(",")];

        visibleRows.forEach((row) => {
            const cells = Array.from(row.cells).map((cell) => {
                const text = cell.textContent.replace(/\s+/g, " ").trim();
                return /[",\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
            });
            lines.push(cells.join(","));
        });

        const blob = new Blob([lines.join("\n")], { type: "text/csv;charset=utf-8;" });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = "audit-log-export.csv";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    });
}

applyAuditFilters();
</script>

<?php require __DIR__ . "/footer.php"; ?>
