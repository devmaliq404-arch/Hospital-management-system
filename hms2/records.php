<?php
session_start();
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/partials/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $doctor    = trim($_POST['doctor_name'] ?? '');
        $diagnosis = trim($_POST['diagnosis'] ?? '');
        $treatment = trim($_POST['treatment'] ?? '');
        $date      = trim($_POST['record_date'] ?? '');

        $exists = (int)db_value($conn, "SELECT COUNT(*) FROM patients WHERE id = $patientId");

        if (!$exists) {
            flash('Please select a patient.', 'error');
        } elseif ($diagnosis === '') {
            flash('Please enter a diagnosis.', 'error');
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > date('Y-m-d')) {
            flash('Please choose a valid date (not in the future).', 'error');
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO medical_records (patient_id, doctor_name, diagnosis, treatment, record_date)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("issss", $patientId, $doctor, $diagnosis, $treatment, $date);
            $stmt->execute();
            $stmt->close();
            flash('Medical record saved.');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM medical_records WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        flash('Record deleted.');
    }
    redirect('records.php');
}

$patients = db_rows($conn, "SELECT id, name, patient_code FROM patients ORDER BY name");
$doctors  = db_rows($conn, "SELECT name FROM doctors ORDER BY name");

$search  = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;
$like    = '%' . $search . '%';

$stmt = $conn->prepare(
    "SELECT COUNT(*) FROM medical_records r
     LEFT JOIN patients p ON p.id = r.patient_id
     WHERE p.name LIKE ? OR r.diagnosis LIKE ? OR r.doctor_name LIKE ?"
);
$stmt->bind_param("sss", $like, $like, $like);
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();
$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = $conn->prepare(
    "SELECT r.id, r.doctor_name, r.diagnosis, r.treatment, r.record_date,
            COALESCE(p.name, '(deleted patient)') AS pname, p.patient_code
     FROM medical_records r
     LEFT JOIN patients p ON p.id = r.patient_id
     WHERE p.name LIKE ? OR r.diagnosis LIKE ? OR r.doctor_name LIKE ?
     ORDER BY r.record_date DESC, r.id DESC
     LIMIT ? OFFSET ?"
);
$stmt->bind_param("sssii", $like, $like, $like, $perPage, $offset);
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = 'Medical records';
$active    = 'records';
include __DIR__ . '/partials/header.php';
?>

<div class="page-head">
    <div>
        <h1>Medical records</h1>
        <p><?php echo (int)$total; ?> record<?php echo $total == 1 ? '' : 's'; ?> on file</p>
    </div>
</div>

<div class="stack">

<div class="panel">
    <h2>New record</h2>
    <?php if (empty($patients)): ?>
        <p style="color:var(--muted)">No patients yet. <a href="add_patient.php" style="color:var(--brand);font-weight:600">Register a patient</a> first.</p>
    <?php else: ?>
    <form method="post">
        <input type="hidden" name="action" value="add">
        <div class="form-grid">
            <div class="field">
                <label for="patient_id">Patient *</label>
                <select id="patient_id" name="patient_id" required>
                    <option value="">Select patient</option>
                    <?php foreach ($patients as $p): ?>
                        <option value="<?php echo (int)$p['id']; ?>"><?php echo e($p['name'] . ($p['patient_code'] ? ' (' . $p['patient_code'] . ')' : '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="doctor_name">Attending doctor</label>
                <select id="doctor_name" name="doctor_name">
                    <option value="">Not specified</option>
                    <?php foreach ($doctors as $d): ?>
                        <option value="<?php echo e($d['name']); ?>"><?php echo e($d['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="diagnosis">Diagnosis *</label>
                <input type="text" id="diagnosis" name="diagnosis" required>
            </div>
            <div class="field">
                <label for="record_date">Date *</label>
                <input type="date" id="record_date" name="record_date" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="field full">
                <label for="treatment">Treatment / notes</label>
                <textarea id="treatment" name="treatment" rows="3"></textarea>
            </div>
        </div>
        <div class="form-actions"><button class="btn" type="submit">Save record</button></div>
    </form>
    <?php endif; ?>
</div>

<div class="panel">
    <h2>All records</h2>
    <form method="get" class="toolbar">
        <input type="search" name="q" placeholder="Search by patient, diagnosis or doctor" value="<?php echo e($search); ?>">
        <button class="btn" type="submit">Search</button>
        <?php if ($search !== ''): ?><a href="records.php" class="btn btn-ghost">Clear</a><?php endif; ?>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Patient</th><th>Doctor</th><th>Diagnosis</th><th>Treatment / notes</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($records)): ?>
                <tr><td colspan="6" class="empty">No records found.</td></tr>
            <?php else: foreach ($records as $r): ?>
                <tr>
                    <td><?php echo date('j M Y', strtotime($r['record_date'])); ?></td>
                    <td><b><?php echo e($r['pname']); ?></b></td>
                    <td><?php echo e($r['doctor_name'] ?: '-'); ?></td>
                    <td><?php echo e($r['diagnosis']); ?></td>
                    <td title="<?php echo e($r['treatment']); ?>">
                        <?php echo e(mb_strimwidth((string)$r['treatment'], 0, 50, '...') ?: '-'); ?>
                    </td>
                    <td>
                        <form method="post" style="margin:0" onsubmit="return confirm('Delete this record?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                            <button class="btn btn-danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php echo pager($page, $totalPages, ['q' => $search]); ?>
</div>

</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
