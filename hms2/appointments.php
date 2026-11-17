<?php
session_start();
require_once __DIR__ . '/connect.php'; // creates $conn (mysqli)

$statuses = ['Scheduled', 'Waiting', 'In progress', 'Completed', 'Cancelled'];

function statusClass($s) {
    switch (strtolower($s)) {
        case 'completed':   return 'st-done';
        case 'in progress': return 'st-live';
        case 'waiting':     return 'st-wait';
        case 'cancelled':   return 'st-cancel';
        default:            return 'st-sched';
    }
}

// ---- POST actions: change status / delete ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);

    if (isset($_POST['new_status']) && in_array($_POST['new_status'], $statuses, true)) {
        $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $_POST['new_status'], $id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['flash'] = 'Appointment status updated.';
    } elseif (isset($_POST['delete'])) {
        $stmt = $conn->prepare("DELETE FROM appointments WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['flash'] = 'Appointment deleted.';
    }

    // Return to the same filtered view
    $back = 'appointments.php' . (!empty($_POST['return']) ? '?' . ltrim($_POST['return'], '?') : '');
    header("Location: " . $back);
    exit;
}

// ---- Filters ----
$dateParam = $_GET['date'] ?? date('Y-m-d');          // default: today
$showAll   = ($dateParam === 'all');
$date      = $showAll ? '' : $dateParam;
if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}
$statusFilter = $_GET['status'] ?? '';
if ($statusFilter !== '' && !in_array($statusFilter, $statuses, true)) {
    $statusFilter = '';
}
$search = trim($_GET['q'] ?? '');

$where  = [];
$types  = '';
$params = [];

if ($date !== '')         { $where[] = "appt_date = ?";  $types .= 's'; $params[] = $date; }
if ($statusFilter !== '') { $where[] = "status = ?";     $types .= 's'; $params[] = $statusFilter; }
if ($search !== '') {
    $where[] = "(patient_name LIKE ? OR doctor_name LIKE ? OR department LIKE ?)";
    $types .= 'sss';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ---- Pagination ----
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$stmt = $conn->prepare("SELECT COUNT(*) FROM appointments $whereSql");
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();
$totalPages = max(1, (int)ceil($total / $perPage));

$sql = "SELECT id, patient_name, doctor_name, department, appt_date, appt_time, status
        FROM appointments $whereSql
        ORDER BY appt_date DESC, STR_TO_DATE(appt_time, '%h:%i %p') ASC
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$allTypes  = $types . 'ii';
$allParams = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Query string used to return to this same view after an action
$returnQs = http_build_query(array_filter([
    'date' => $showAll ? 'all' : $date,
    'status' => $statusFilter,
    'q' => $search,
    'page' => $page > 1 ? $page : null,
]));

$pageTitle = 'Appointments';
$active    = 'appointments';
include __DIR__ . '/partials/header.php';
?>

<div class="page-head">
    <div>
        <h1>Appointments</h1>
        <p>
            <?php echo (int)$total; ?> appointment<?php echo $total == 1 ? '' : 's'; ?>
            <?php echo $showAll ? 'across all dates' : 'on ' . date('D, j M Y', strtotime($date)); ?>
        </p>
    </div>
    <a href="book_appointment.php" class="btn">+ Book appointment</a>
</div>

<div class="panel">
    <form method="get" class="toolbar">
        <input type="date" name="date" value="<?php echo $showAll ? '' : htmlspecialchars($date); ?>"
               style="padding:10px;border:1px solid var(--line);border-radius:8px;font:inherit">
        <select name="status" style="padding:10px;border:1px solid var(--line);border-radius:8px;font:inherit">
            <option value="">All statuses</option>
            <?php foreach ($statuses as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $statusFilter === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
            <?php endforeach; ?>
        </select>
        <input type="search" name="q" placeholder="Search patient, doctor or department"
               value="<?php echo htmlspecialchars($search); ?>">
        <button class="btn" type="submit">Filter</button>
        <a href="appointments.php?date=all" class="btn btn-ghost">All dates</a>
        <a href="appointments.php" class="btn btn-ghost">Today</a>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Patient</th><th>Doctor</th><th>Department</th>
                    <th>Date</th><th>Time</th><th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="7" class="empty">No appointments found for this view.</td></tr>
            <?php else: foreach ($rows as $a): ?>
                <tr>
                    <td><b><?php echo htmlspecialchars($a['patient_name']); ?></b></td>
                    <td><?php echo htmlspecialchars($a['doctor_name']); ?></td>
                    <td><?php echo htmlspecialchars($a['department']); ?></td>
                    <td><?php echo date('j M Y', strtotime($a['appt_date'])); ?></td>
                    <td><?php echo htmlspecialchars($a['appt_time']); ?></td>
                    <td>
                        <form method="post" style="margin:0">
                            <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                            <input type="hidden" name="return" value="<?php echo htmlspecialchars($returnQs); ?>">
                            <select name="new_status" onchange="this.form.submit()"
                                    class="badge <?php echo statusClass($a['status']); ?>"
                                    style="border:0;cursor:pointer;font:inherit;font-size:12px;font-weight:600">
                                <?php foreach ($statuses as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo $a['status'] === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                    <td>
                        <form method="post" style="margin:0"
                              onsubmit="return confirm('Delete this appointment?');">
                            <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                            <input type="hidden" name="return" value="<?php echo htmlspecialchars($returnQs); ?>">
                            <button type="submit" name="delete" value="1" class="btn btn-danger">Delete</button>
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
            $qs = http_build_query(array_filter([
                'date' => $showAll ? 'all' : $date,
                'status' => $statusFilter,
                'q' => $search,
                'page' => $i,
            ])); ?>
            <?php if ($i === $page): ?>
                <span class="current"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="appointments.php?<?php echo $qs; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
