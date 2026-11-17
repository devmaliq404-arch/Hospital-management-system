<?php
session_start();
require_once __DIR__ . '/connect.php'; // creates $conn (mysqli)

// ---- Delete a patient (POST) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];
    $stmt = $conn->prepare("DELETE FROM patients WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['flash'] = 'Patient deleted.';
    header("Location: patients.php");
    exit;
}

// ---- Search + pagination ----
$search  = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;
$like    = '%' . $search . '%';

$stmt = $conn->prepare(
    "SELECT COUNT(*) FROM patients
     WHERE name LIKE ? OR phone LIKE ? OR patient_code LIKE ?"
);
$stmt->bind_param("sss", $like, $like, $like);
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();

$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = $conn->prepare(
    "SELECT id, patient_code, name, age, gender, phone, blood_group, created_at
     FROM patients
     WHERE name LIKE ? OR phone LIKE ? OR patient_code LIKE ?
     ORDER BY id DESC
     LIMIT ? OFFSET ?"
);
$stmt->bind_param("sssii", $like, $like, $like, $perPage, $offset);
$stmt->execute();
$patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = 'Patients';
$active    = 'patients';
include __DIR__ . '/partials/header.php';
?>

<div class="page-head">
    <div>
        <h1>Patients</h1>
        <p><?php echo (int)$total; ?> registered patient<?php echo $total == 1 ? '' : 's'; ?></p>
    </div>
    <a href="add_patient.php" class="btn">+ Register patient</a>
</div>

<div class="panel">
    <form method="get" class="toolbar">
        <input type="search" name="q" placeholder="Search by name, phone or patient ID"
               value="<?php echo htmlspecialchars($search); ?>">
        <button class="btn" type="submit">Search</button>
        <?php if ($search !== ''): ?>
            <a href="patients.php" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th><th>Name</th><th>Age</th><th>Gender</th>
                    <th>Phone</th><th>Blood</th><th>Registered</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($patients)): ?>
                <tr><td colspan="8" class="empty">No patients found.</td></tr>
            <?php else: foreach ($patients as $p): ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['patient_code'] ?: 'PT-' . str_pad($p['id'], 5, '0', STR_PAD_LEFT)); ?></td>
                    <td><b><?php echo htmlspecialchars($p['name']); ?></b></td>
                    <td><?php echo (int)$p['age']; ?></td>
                    <td><?php echo htmlspecialchars($p['gender']); ?></td>
                    <td><?php echo htmlspecialchars($p['phone'] ?: '-'); ?></td>
                    <td><?php echo $p['blood_group'] ? '<span class="badge">' . htmlspecialchars($p['blood_group']) . '</span>' : '-'; ?></td>
                    <td><?php echo date('j M Y', strtotime($p['created_at'])); ?></td>
                    <td>
                        <form method="post" style="margin:0"
                              onsubmit="return confirm('Delete this patient? This cannot be undone.');">
                            <input type="hidden" name="delete_id" value="<?php echo (int)$p['id']; ?>">
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pager">
        <?php for ($i = 1; $i <= $totalPages; $i++):
            $url = 'patients.php?page=' . $i . ($search !== '' ? '&q=' . urlencode($search) : ''); ?>
            <?php if ($i === $page): ?>
                <span class="current"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="<?php echo $url; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
