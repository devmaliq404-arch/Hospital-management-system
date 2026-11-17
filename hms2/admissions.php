<?php
session_start();
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/partials/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---- Admit a patient ----
    if ($action === 'admit') {
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $bedId     = (int)($_POST['bed_id'] ?? 0);
        $reason    = trim($_POST['reason'] ?? '');

        if ($patientId < 1 || $bedId < 1) {
            flash('Please choose a patient and a bed.', 'error');
        } else {
            $already = (int)db_value($conn, "SELECT COUNT(*) FROM admissions WHERE patient_id = $patientId AND status = 'Admitted'");
            if ($already > 0) {
                flash('That patient is already admitted.', 'error');
            } else {
                // Take the bed only if it is still available
                $upd = $conn->prepare("UPDATE beds SET status = 'occupied' WHERE id = ? AND status = 'available'");
                $upd->bind_param("i", $bedId);
                $upd->execute();
                $taken = $upd->affected_rows === 1;
                $upd->close();

                if ($taken) {
                    $ins = $conn->prepare("INSERT INTO admissions (patient_id, bed_id, reason, status) VALUES (?, ?, ?, 'Admitted')");
                    $ins->bind_param("iis", $patientId, $bedId, $reason);
                    $ins->execute();
                    $ins->close();
                    flash('Patient admitted.');
                } else {
                    flash('That bed is no longer available.', 'error');
                }
            }
        }
    }

    // ---- Discharge ----
    elseif ($action === 'discharge') {
        $id = (int)($_POST['id'] ?? 0);
        $bedId = (int)db_value($conn, "SELECT bed_id FROM admissions WHERE id = $id AND status = 'Admitted'", 0);
        if ($bedId > 0) {
            $conn->query("UPDATE admissions SET status = 'Discharged', discharged_at = NOW() WHERE id = $id");
            $conn->query("UPDATE beds SET status = 'available' WHERE id = $bedId");
            flash('Patient discharged and bed released.');
        }
    }

    // ---- Add beds to a ward ----
    elseif ($action === 'add_beds') {
        $ward  = trim($_POST['ward'] ?? '');
        $count = (int)($_POST['count'] ?? 0);
        if ($ward === '' || $count < 1 || $count > 50) {
            flash('Enter a ward name and a number of beds between 1 and 50.', 'error');
        } else {
            $ins = $conn->prepare("INSERT INTO beds (ward, status) VALUES (?, 'available')");
            for ($i = 0; $i < $count; $i++) {
                $ins->bind_param("s", $ward);
                $ins->execute();
            }
            $ins->close();
            flash($count . ' bed(s) added to ' . $ward . '.');
        }
    }

    // ---- Remove an empty bed ----
    elseif ($action === 'delete_bed') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM beds WHERE id = ? AND status = 'available'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        flash('Bed removed.');
    }

    redirect('admissions.php');
}

// ---- Data for the page ----
$totalBeds    = (int)db_value($conn, "SELECT COUNT(*) FROM beds");
$occupiedBeds = (int)db_value($conn, "SELECT COUNT(*) FROM beds WHERE status = 'occupied'");
$availBeds    = $totalBeds - $occupiedBeds;
$percent      = $totalBeds > 0 ? round($occupiedBeds / $totalBeds * 100) : 0;

$wards = db_rows($conn, "SELECT ward, SUM(status = 'occupied') AS occ, COUNT(*) AS total FROM beds GROUP BY ward ORDER BY ward");
$freeBeds = db_rows($conn, "SELECT id, ward FROM beds WHERE status = 'available' ORDER BY ward, id");
$freePatients = db_rows($conn,
    "SELECT id, name, patient_code FROM patients
     WHERE id NOT IN (SELECT patient_id FROM admissions WHERE status = 'Admitted')
     ORDER BY name");

$current = db_rows($conn,
    "SELECT a.id, a.reason, a.admitted_at, DATEDIFF(NOW(), a.admitted_at) AS days,
            COALESCE(p.name, '(deleted patient)') AS pname, p.patient_code, b.ward, b.id AS bed_id
     FROM admissions a
     LEFT JOIN patients p ON p.id = a.patient_id
     LEFT JOIN beds b ON b.id = a.bed_id
     WHERE a.status = 'Admitted'
     ORDER BY a.admitted_at DESC");

$history = db_rows($conn,
    "SELECT a.reason, a.admitted_at, a.discharged_at,
            COALESCE(p.name, '(deleted patient)') AS pname, b.ward
     FROM admissions a
     LEFT JOIN patients p ON p.id = a.patient_id
     LEFT JOIN beds b ON b.id = a.bed_id
     WHERE a.status = 'Discharged'
     ORDER BY a.discharged_at DESC LIMIT 8");

$emptyBeds = db_rows($conn, "SELECT id, ward FROM beds WHERE status = 'available' ORDER BY ward, id");

$pageTitle = 'Admissions & beds';
$active    = 'admissions';
include __DIR__ . '/partials/header.php';
?>

<div class="page-head">
    <div>
        <h1>Admissions &amp; beds</h1>
        <p>Admit patients, manage wards and discharge.</p>
    </div>
</div>

<div class="cards">
    <div class="card-stat"><b><?php echo $totalBeds; ?></b><span>Total beds</span></div>
    <div class="card-stat <?php echo $percent >= 85 ? 'bad' : ''; ?>"><b><?php echo $occupiedBeds; ?></b><span>Occupied (<?php echo $percent; ?>%)</span></div>
    <div class="card-stat"><b><?php echo $availBeds; ?></b><span>Available</span></div>
    <div class="card-stat"><b><?php echo count($current); ?></b><span>Admitted patients</span></div>
</div>

<div class="stack">

<div class="two">
    <div class="panel" id="admit">
        <h2>Admit a patient</h2>
        <?php if (empty($freePatients) || empty($freeBeds)): ?>
            <p style="color:var(--muted)">
                <?php echo empty($freePatients) ? 'No patients are available to admit. <a href="add_patient.php" style="color:var(--brand);font-weight:600">Register one</a>.' : 'There are no free beds. Add beds in the panel on the right.'; ?>
            </p>
        <?php else: ?>
        <form method="post">
            <input type="hidden" name="action" value="admit">
            <div class="form-grid" style="grid-template-columns:1fr">
                <div class="field">
                    <label for="patient_id">Patient *</label>
                    <select id="patient_id" name="patient_id" required>
                        <option value="">Select patient</option>
                        <?php foreach ($freePatients as $p): ?>
                            <option value="<?php echo (int)$p['id']; ?>"><?php echo e($p['name'] . ($p['patient_code'] ? ' (' . $p['patient_code'] . ')' : '')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="bed_id">Bed *</label>
                    <select id="bed_id" name="bed_id" required>
                        <option value="">Select bed</option>
                        <?php foreach ($freeBeds as $b): ?>
                            <option value="<?php echo (int)$b['id']; ?>"><?php echo e($b['ward'] . ' - Bed #' . $b['id']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="reason">Reason for admission</label>
                    <input type="text" id="reason" name="reason" placeholder="e.g. Severe malaria, observation">
                </div>
            </div>
            <div class="form-actions"><button class="btn" type="submit">Admit patient</button></div>
        </form>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h2>Wards</h2>
        <?php if (empty($wards)): ?>
            <p style="color:var(--muted)">No wards yet. Add some beds below.</p>
        <?php else: foreach ($wards as $w):
            $pct = $w['total'] > 0 ? round($w['occ'] / $w['total'] * 100) : 0; ?>
            <div class="bar-row">
                <div class="bar-top"><span><?php echo e($w['ward']); ?></span><span style="color:var(--muted)"><?php echo (int)$w['occ']; ?>/<?php echo (int)$w['total']; ?> beds</span></div>
                <div class="bar"><i class="<?php echo $pct >= 85 ? 'high' : ''; ?>" style="width:<?php echo $pct; ?>%"></i></div>
            </div>
        <?php endforeach; endif; ?>

        <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
        <form method="post" class="row-form" style="flex-wrap:wrap">
            <input type="hidden" name="action" value="add_beds">
            <input type="text" name="ward" placeholder="Ward name" list="wardlist" required style="flex:1;min-width:140px">
            <datalist id="wardlist"><?php foreach ($wards as $w): ?><option value="<?php echo e($w['ward']); ?>"><?php endforeach; ?></datalist>
            <input type="number" name="count" min="1" max="50" value="1" required style="width:80px">
            <button class="btn btn-sm" type="submit">Add beds</button>
        </form>
    </div>
</div>

<div class="panel">
    <h2>Currently admitted</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Patient</th><th>Ward / bed</th><th>Reason</th><th>Admitted</th><th>Stay</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($current)): ?>
                <tr><td colspan="6" class="empty">No patients are currently admitted.</td></tr>
            <?php else: foreach ($current as $a): ?>
                <tr>
                    <td><b><?php echo e($a['pname']); ?></b></td>
                    <td><?php echo e(($a['ward'] ?: '-') . ' - Bed #' . $a['bed_id']); ?></td>
                    <td><?php echo e($a['reason'] ?: '-'); ?></td>
                    <td><?php echo date('j M Y, g:i A', strtotime($a['admitted_at'])); ?></td>
                    <td><?php echo (int)$a['days']; ?> day<?php echo (int)$a['days'] === 1 ? '' : 's'; ?></td>
                    <td>
                        <form method="post" style="margin:0" onsubmit="return confirm('Discharge this patient?');">
                            <input type="hidden" name="action" value="discharge">
                            <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                            <button class="btn btn-ghost btn-sm" type="submit">Discharge</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="two">
    <div class="panel">
        <h2>Recent discharges</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Patient</th><th>Ward</th><th>Discharged</th></tr></thead>
                <tbody>
                <?php if (empty($history)): ?>
                    <tr><td colspan="3" class="empty">No discharges yet.</td></tr>
                <?php else: foreach ($history as $h): ?>
                    <tr>
                        <td><?php echo e($h['pname']); ?></td>
                        <td><?php echo e($h['ward'] ?: '-'); ?></td>
                        <td><?php echo $h['discharged_at'] ? date('j M Y', strtotime($h['discharged_at'])) : '-'; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <h2>Empty beds</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Ward</th><th>Bed</th><th></th></tr></thead>
                <tbody>
                <?php if (empty($emptyBeds)): ?>
                    <tr><td colspan="3" class="empty">No empty beds.</td></tr>
                <?php else: foreach ($emptyBeds as $b): ?>
                    <tr>
                        <td><?php echo e($b['ward']); ?></td>
                        <td>#<?php echo (int)$b['id']; ?></td>
                        <td>
                            <form method="post" style="margin:0" onsubmit="return confirm('Remove this bed?');">
                                <input type="hidden" name="action" value="delete_bed">
                                <input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>">
                                <button class="btn btn-danger" type="submit">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
