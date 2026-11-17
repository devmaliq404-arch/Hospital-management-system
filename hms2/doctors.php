<?php
session_start();
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/partials/helpers.php';

$specialties = [
    'General Medicine', 'Cardiology', 'Pediatrics', 'Orthopedics', 'Neurology',
    'Surgery', 'Gynecology', 'Dermatology', 'Radiology', 'Emergency',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'add') {
        $name  = trim($_POST['name'] ?? '');
        $spec  = trim($_POST['specialty'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (mb_strlen($name) < 2 || $spec === '') {
            flash("Please enter the doctor's name and specialty.", 'error');
        } elseif ($phone !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
            flash('Phone number can only contain digits, spaces, + and -.', 'error');
        } else {
            $stmt = $conn->prepare("INSERT INTO doctors (name, specialty, phone, status) VALUES (?, ?, ?, 'Active')");
            $stmt->bind_param("sss", $name, $spec, $phone);
            $stmt->execute();
            $stmt->close();
            flash('Doctor added.');
        }
    } elseif ($action === 'toggle') {
        $stmt = $conn->prepare("UPDATE doctors SET status = IF(status = 'Active', 'On leave', 'Active') WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        flash('Doctor status updated.');
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM doctors WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        flash('Doctor removed.');
    }
    redirect('doctors.php');
}

$search = trim($_GET['q'] ?? '');
$like = '%' . $search . '%';
$stmt = $conn->prepare(
    "SELECT d.id, d.name, d.specialty, d.phone, d.status,
            (SELECT COUNT(*) FROM appointments a
             WHERE a.doctor_name = d.name AND a.appt_date = CURDATE() AND a.status <> 'Cancelled') AS today_count
     FROM doctors d
     WHERE d.name LIKE ? OR d.specialty LIKE ?
     ORDER BY d.name ASC"
);
$stmt->bind_param("ss", $like, $like);
$stmt->execute();
$doctors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$staff = db_rows($conn, "SELECT id, name, email FROM users ORDER BY id DESC LIMIT 50");

$pageTitle = 'Doctors & staff';
$active    = 'doctors';
include __DIR__ . '/partials/header.php';
?>

<div class="page-head">
    <div>
        <h1>Doctors &amp; staff</h1>
        <p><?php echo count($doctors); ?> doctor<?php echo count($doctors) === 1 ? '' : 's'; ?> listed</p>
    </div>
</div>

<div class="stack">

<div class="panel">
    <h2>Add a doctor</h2>
    <form method="post">
        <input type="hidden" name="action" value="add">
        <div class="form-grid">
            <div class="field">
                <label for="name">Full name *</label>
                <input type="text" id="name" name="name" placeholder="Dr. Jane Doe" required>
            </div>
            <div class="field">
                <label for="specialty">Specialty *</label>
                <input type="text" id="specialty" name="specialty" list="specs" required>
                <datalist id="specs">
                    <?php foreach ($specialties as $s): ?><option value="<?php echo e($s); ?>"><?php endforeach; ?>
                </datalist>
            </div>
            <div class="field">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone">
            </div>
        </div>
        <div class="form-actions"><button class="btn" type="submit">Add doctor</button></div>
    </form>
</div>

<div class="panel">
    <h2>Doctors</h2>
    <form method="get" class="toolbar">
        <input type="search" name="q" placeholder="Search by name or specialty" value="<?php echo e($search); ?>">
        <button class="btn" type="submit">Search</button>
        <?php if ($search !== ''): ?><a href="doctors.php" class="btn btn-ghost">Clear</a><?php endif; ?>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Specialty</th><th>Phone</th><th>Today</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($doctors)): ?>
                <tr><td colspan="6" class="empty">No doctors found.</td></tr>
            <?php else: foreach ($doctors as $d): ?>
                <tr>
                    <td><b><?php echo e($d['name']); ?></b></td>
                    <td><?php echo e($d['specialty']); ?></td>
                    <td><?php echo e($d['phone'] ?: '-'); ?></td>
                    <td><?php echo (int)$d['today_count']; ?> appt<?php echo (int)$d['today_count'] === 1 ? '' : 's'; ?></td>
                    <td><?php echo badge($d['status'] ?: 'Active'); ?></td>
                    <td>
                        <form method="post" class="row-form" style="justify-content:flex-end"
                              onsubmit="return !event.submitter || event.submitter.value !== 'delete' || confirm('Remove this doctor?');">
                            <input type="hidden" name="id" value="<?php echo (int)$d['id']; ?>">
                            <button type="submit" name="action" value="toggle" class="btn btn-ghost btn-sm">Toggle leave</button>
                            <button type="submit" name="action" value="delete" class="btn btn-danger">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="panel">
    <h2>Staff accounts</h2>
    <p style="color:var(--muted);margin-top:-6px">People who can sign in to this system.</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Name</th><th>Email</th></tr></thead>
            <tbody>
            <?php if (empty($staff)): ?>
                <tr><td colspan="3" class="empty">No staff accounts found.</td></tr>
            <?php else: foreach ($staff as $u): ?>
                <tr>
                    <td><?php echo (int)$u['id']; ?></td>
                    <td><b><?php echo e($u['name']); ?></b></td>
                    <td><?php echo e($u['email']); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
