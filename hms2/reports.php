<?php
session_start();
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/partials/helpers.php';

$allowed = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days', 365 => 'Last 12 months'];
$days = (int)($_GET['days'] ?? 30);
if (!isset($allowed[$days])) $days = 30;

// ---- Summary numbers ----
$newPatients = (int)db_value($conn, "SELECT COUNT(*) FROM patients WHERE created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)");
$totalPatients = (int)db_value($conn, "SELECT COUNT(*) FROM patients");
$apptCount   = (int)db_value($conn, "SELECT COUNT(*) FROM appointments WHERE appt_date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)");
$revenue     = (float)db_value($conn, "SELECT SUM(amount) FROM bills WHERE status = 'Paid' AND bill_date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)");
$outstanding = (float)db_value($conn, "SELECT SUM(amount) FROM bills WHERE status = 'Unpaid'");
$admissions  = (int)db_value($conn, "SELECT COUNT(*) FROM admissions WHERE admitted_at >= DATE_SUB(NOW(), INTERVAL $days DAY)");
$labCount    = (int)db_value($conn, "SELECT COUNT(*) FROM lab_tests WHERE test_date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)");

// ---- Breakdowns ----
$byStatus = db_rows($conn, "SELECT status AS label, COUNT(*) AS value FROM appointments
                            WHERE appt_date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
                            GROUP BY status ORDER BY value DESC");
$byDept   = db_rows($conn, "SELECT department AS label, COUNT(*) AS value FROM appointments
                            WHERE appt_date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
                            GROUP BY department ORDER BY value DESC LIMIT 6");
$byGender = db_rows($conn, "SELECT gender AS label, COUNT(*) AS value FROM patients GROUP BY gender ORDER BY value DESC");
$byLab    = db_rows($conn, "SELECT status AS label, COUNT(*) AS value FROM lab_tests
                            WHERE test_date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
                            GROUP BY status ORDER BY value DESC");
$monthly  = db_rows($conn, "SELECT DATE_FORMAT(bill_date, '%b %Y') AS label, SUM(amount) AS value, DATE_FORMAT(bill_date, '%Y-%m') AS ym
                            FROM bills
                            WHERE status = 'Paid' AND bill_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
                            GROUP BY ym, label ORDER BY ym ASC");
$topMeds  = db_rows($conn, "SELECT name AS label, stock AS value FROM medications ORDER BY stock ASC LIMIT 5");

$totalBeds    = (int)db_value($conn, "SELECT COUNT(*) FROM beds");
$occupiedBeds = (int)db_value($conn, "SELECT COUNT(*) FROM beds WHERE status = 'occupied'");
$bedPct       = $totalBeds > 0 ? round($occupiedBeds / $totalBeds * 100) : 0;

// Draws a list of horizontal bars
function bars($rows, $money = false) {
    if (empty($rows)) {
        return '<p style="color:var(--muted)">No data for this period.</p>';
    }
    $max = 0;
    foreach ($rows as $r) $max = max($max, (float)$r['value']);
    $out = '';
    foreach ($rows as $r) {
        $pct = $max > 0 ? round($r['value'] / $max * 100) : 0;
        $val = $money ? money($r['value']) : (int)$r['value'];
        $out .= '<div class="bar-row"><div class="bar-top"><span>' . e($r['label'] ?: 'Unknown') . '</span><span style="color:var(--muted)">' . $val . '</span></div>'
              . '<div class="bar"><i style="width:' . $pct . '%"></i></div></div>';
    }
    return $out;
}

$pageTitle = 'Reports';
$active    = 'reports';
include __DIR__ . '/partials/header.php';
?>

<div class="page-head">
    <div>
        <h1>Reports</h1>
        <p><?php echo e($allowed[$days]); ?> &middot; generated <?php echo date('j M Y, g:i A'); ?></p>
    </div>
    <div class="toolbar no-print" style="margin:0">
        <?php foreach ($allowed as $d => $label): ?>
            <a href="reports.php?days=<?php echo $d; ?>" class="btn btn-sm <?php echo $days === $d ? '' : 'btn-ghost'; ?>"><?php echo e($label); ?></a>
        <?php endforeach; ?>
        <button class="btn btn-ghost btn-sm" onclick="window.print()">Print</button>
    </div>
</div>

<div class="cards">
    <div class="card-stat"><b><?php echo $newPatients; ?></b><span>New patients (<?php echo $totalPatients; ?> total)</span></div>
    <div class="card-stat"><b><?php echo $apptCount; ?></b><span>Appointments</span></div>
    <div class="card-stat"><b><?php echo $admissions; ?></b><span>Admissions</span></div>
    <div class="card-stat"><b><?php echo $labCount; ?></b><span>Lab tests</span></div>
    <div class="card-stat"><b style="color:var(--ok)"><?php echo money($revenue); ?></b><span>Revenue collected</span></div>
    <div class="card-stat warn"><b><?php echo money($outstanding); ?></b><span>Outstanding bills</span></div>
</div>

<div class="stack">
    <div class="two">
        <div class="panel"><h2>Appointments by status</h2><?php echo bars($byStatus); ?></div>
        <div class="panel"><h2>Appointments by department</h2><?php echo bars($byDept); ?></div>
    </div>

    <div class="two">
        <div class="panel"><h2>Revenue by month (paid)</h2><?php echo bars($monthly, true); ?></div>
        <div class="panel"><h2>Lab tests by status</h2><?php echo bars($byLab); ?></div>
    </div>

    <div class="two">
        <div class="panel"><h2>Patients by gender</h2><?php echo bars($byGender); ?></div>
        <div class="panel">
            <h2>Bed occupancy</h2>
            <div class="bar-row">
                <div class="bar-top"><span>Occupied beds</span><span style="color:var(--muted)"><?php echo $occupiedBeds; ?>/<?php echo $totalBeds; ?> (<?php echo $bedPct; ?>%)</span></div>
                <div class="bar"><i class="<?php echo $bedPct >= 85 ? 'high' : ''; ?>" style="width:<?php echo $bedPct; ?>%"></i></div>
            </div>
            <h2 style="margin-top:22px">Lowest medication stock</h2>
            <?php echo bars($topMeds); ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
