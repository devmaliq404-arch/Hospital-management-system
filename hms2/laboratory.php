<?php
session_start();
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/partials/helpers.php';

$tests = [
    'Full blood count', 'Malaria parasite test', 'Blood glucose', 'Urinalysis',
    'Lipid profile', 'Liver function test', 'Kidney function test', 'HIV screening',
    'Pregnancy test', 'Hepatitis B screening', 'X-ray', 'Ultrasound', 'ECG',
];
$statuses = ['Pending', 'In progress', 'Completed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $test      = trim($_POST['test_name'] ?? '');
        $by        = trim($_POST['requested_by'] ?? '');
        $exists    = (int)db_value($conn, "SELECT COUNT(*) FROM patients WHERE id = $patientId");

        if (!$exists || $test === '') {
            flash('Please choose a patient and a test.', 'error');
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO lab_tests (patient_id, test_name, requested_by, status, test_date)
                 VALUES (?, ?, ?, 'Pending', CURDATE())"
            );
            $stmt->bind_param("iss", $patientId, $test, $by);
            $stmt->execute();
            $stmt->close();
            flash('Lab test requested.');
        }
    } elseif ($action === 'update') {
        $id     = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $result = mb_substr(trim($_POST['result'] ?? ''), 0, 255);

        if (in_array($status, $statuses, true)) {
            $stmt = $conn->prepare("UPDATE lab_tests SET status = ?, result = ? WHERE id = ?");
            $stmt->bind_param("ssi", $status, $result, $id);
            $stmt->execute();
            $stmt->close();
            flash('Lab test updated.');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM lab_tests WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        flash('Lab test deleted.');
    }
    redirect('laboratory.php' . (!empty($_POST['return']) ? '?' . ltrim($_POST['return'], '?') : ''));
}

$patients = db_rows($conn, "SELECT id, name, patient_code FROM patients ORDER BY name");
$doctors  = db_rows($conn, "SELECT name FROM doctors ORDER BY name");

$statusFilter = $_GET['status'] ?? '';
if (!in_array($statusFilter, $statuses, true)) $statusFilter = '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$where = $statusFilter !== '' ? "WHERE t.status = ?" : "";

$stmt = $conn->prepare("SELECT COUNT(*) FROM lab_tests t $where");
if ($where) $stmt->bind_param("s", $statusFilter);
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();
$totalPages = max(1, (int)ceil($total / $perPage));

$sql = "SELECT t.id, t.test_name, t.requested_by, t.status, t.result, t.test_date,
               COALESCE(p.name, '(deleted patient)') AS pname
        FROM lab_tests t LEFT JOIN patients p ON p.id = t.patient_id
        $where ORDER BY t.id DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if ($where) $stmt->bind_param("sii", $statusFilter, $perPage, $offset);
else        $stmt->bind_param("ii", $perPage, $offset);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$counts = ['Pending' => 0, 'In progress' => 0, 'Completed' => 0];
foreach (db_rows($conn, "SELECT status, COUNT(*) AS c FROM lab_tests GROUP BY status") as $c) {
    $counts[$c['status']] = (int)$c['c'];
}

$returnQs = http_build_query(array_filter(['status' => $statusFilter, 'page' => $page > 1 ? $page : null]));

$pageTitle = 'Laboratory';
$active    = 'laboratory';
include __DIR__ . '/partials/header.php';
?>

<div class="page-head">
    <div>
        <h1>Laboratory</h1>
        <p>Request tests and record results.</p>
    </div>
</div>

<div class="cards">
    <div class="card-stat warn"><b><?php echo $counts['Pending']; ?></b><span>Pending</span></div>
    <div class="card-stat"><b><?php echo $counts['In progress']; ?></b><span>In progress</span></div>
    <div class="card-stat"><b><?php echo $counts['Completed']; ?></b><span>Completed</span></div>
</div>

<div class="stack">

<div class="panel">
    <h2>Request a test</h2>
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
                <label for="test_name">Test *</label>
                <select id="test_name" name="test_name" required>
                    <option value="">Select test</option>
                    <?php foreach ($tests as $t): ?><option value="<?php echo e($t); ?>"><?php echo e($t); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="requested_by">Requested by</label>
                <select id="requested_by" name="requested_by">
                    <option value="">Not specified</option>
                    <?php foreach ($doctors as $d): ?><option value="<?php echo e($d['name']); ?>"><?php echo e($d['name']); ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-actions"><button class="btn" type="submit">Request test</button></div>
    </form>
    <?php endif; ?>
</div>

<div class="panel">
    <h2>Lab requests</h2>
    <div class="toolbar">
        <a href="laboratory.php" class="btn <?php echo $statusFilter === '' ? '' : 'btn-ghost'; ?> btn-sm">All</a>
        <?php foreach ($statuses as $s): ?>
            <a href="laboratory.php?status=<?php echo urlencode($s); ?>" class="btn <?php echo $statusFilter === $s ? '' : 'btn-ghost'; ?> btn-sm"><?php echo e($s); ?></a>
        <?php endforeach; ?>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Patient</th><th>Test</th><th>Requested by</th><th>Status &amp; result</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="6" class="empty">No lab requests found.</td></tr>
            <?php else: foreach ($rows as $t): ?>
                <tr>
                    <td><?php echo date('j M Y', strtotime($t['test_date'])); ?></td>
                    <td><b><?php echo e($t['pname']); ?></b></td>
                    <td><?php echo e($t['test_name']); ?></td>
                    <td><?php echo e($t['requested_by'] ?: '-'); ?></td>
                    <td>
                        <form method="post" class="row-form">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                            <input type="hidden" name="return" value="<?php echo e($returnQs); ?>">
                            <select name="status">
                                <?php foreach ($statuses as $s): ?>
                                    <option value="<?php echo e($s); ?>" <?php echo $t['status'] === $s ? 'selected' : ''; ?>><?php echo e($s); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="result" value="<?php echo e($t['result']); ?>" placeholder="Result" style="width:200px">
                            <button class="btn btn-sm" type="submit">Save</button>
                        </form>
                    </td>
                    <td>
                        <form method="post" style="margin:0" onsubmit="return confirm('Delete this lab request?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                            <input type="hidden" name="return" value="<?php echo e($returnQs); ?>">
                            <button class="btn btn-danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php echo pager($page, $totalPages, ['status' => $statusFilter]); ?>
</div>

</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
