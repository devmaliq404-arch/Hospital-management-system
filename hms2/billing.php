<?php
session_start();
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/partials/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'add') {
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $desc      = trim($_POST['description'] ?? '');
        $amount    = (float)($_POST['amount'] ?? 0);
        $date      = trim($_POST['bill_date'] ?? '');
        $status    = ($_POST['status'] ?? '') === 'Paid' ? 'Paid' : 'Unpaid';
        $exists    = (int)db_value($conn, "SELECT COUNT(*) FROM patients WHERE id = $patientId");

        if (!$exists) {
            flash('Please select a patient.', 'error');
        } elseif ($desc === '' || $amount <= 0) {
            flash('Enter a description and an amount greater than zero.', 'error');
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            flash('Please choose a valid date.', 'error');
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO bills (patient_id, description, amount, status, bill_date)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("isdss", $patientId, $desc, $amount, $status, $date);
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();
            flash('Invoice INV-' . str_pad($newId, 5, '0', STR_PAD_LEFT) . ' created.');
        }
    } elseif ($action === 'toggle') {
        $stmt = $conn->prepare("UPDATE bills SET status = IF(status = 'Paid', 'Unpaid', 'Paid') WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        flash('Payment status updated.');
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM bills WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        flash('Invoice deleted.');
    }
    redirect('billing.php' . (!empty($_POST['return']) ? '?' . ltrim($_POST['return'], '?') : ''));
}

$patients = db_rows($conn, "SELECT id, name, patient_code FROM patients ORDER BY name");

$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
if (!in_array($statusFilter, ['Paid', 'Unpaid'], true)) $statusFilter = '';
$like = '%' . $search . '%';

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$where  = "(p.name LIKE ? OR b.description LIKE ?)";
$types  = "ss";
$params = [$like, $like];
if ($statusFilter !== '') { $where .= " AND b.status = ?"; $types .= "s"; $params[] = $statusFilter; }

$stmt = $conn->prepare("SELECT COUNT(*) FROM bills b LEFT JOIN patients p ON p.id = b.patient_id WHERE $where");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();
$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = $conn->prepare(
    "SELECT b.id, b.description, b.amount, b.status, b.bill_date,
            COALESCE(p.name, '(deleted patient)') AS pname
     FROM bills b LEFT JOIN patients p ON p.id = b.patient_id
     WHERE $where ORDER BY b.id DESC LIMIT ? OFFSET ?"
);
$types2  = $types . "ii";
$params2 = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$bills = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$sum = db_rows($conn,
    "SELECT COALESCE(SUM(amount),0) AS billed,
            COALESCE(SUM(CASE WHEN status='Paid' THEN amount END),0) AS paid,
            COALESCE(SUM(CASE WHEN status='Unpaid' THEN amount END),0) AS unpaid
     FROM bills");
$sum = $sum ? $sum[0] : ['billed' => 0, 'paid' => 0, 'unpaid' => 0];

$returnQs = http_build_query(array_filter(['q' => $search, 'status' => $statusFilter, 'page' => $page > 1 ? $page : null]));

$pageTitle = 'Billing';
$active    = 'billing';
include __DIR__ . '/partials/header.php';
?>

<div class="page-head">
    <div>
        <h1>Billing</h1>
        <p>Create invoices and track payments.</p>
    </div>
</div>

<div class="cards">
    <div class="card-stat"><b><?php echo money($sum['billed']); ?></b><span>Total billed</span></div>
    <div class="card-stat"><b style="color:var(--ok)"><?php echo money($sum['paid']); ?></b><span>Paid</span></div>
    <div class="card-stat warn"><b><?php echo money($sum['unpaid']); ?></b><span>Outstanding</span></div>
</div>

<div class="stack">

<div class="panel" id="new">
    <h2>Create invoice</h2>
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
                <label for="bill_date">Date *</label>
                <input type="date" id="bill_date" name="bill_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="field">
                <label for="description">Description *</label>
                <input type="text" id="description" name="description" placeholder="e.g. Consultation and lab tests" required>
            </div>
            <div class="field">
                <label for="amount">Amount (₦) *</label>
                <input type="number" id="amount" name="amount" min="0.01" step="0.01" required>
            </div>
            <div class="field">
                <label for="status">Payment status</label>
                <select id="status" name="status">
                    <option value="Unpaid">Unpaid</option>
                    <option value="Paid">Paid</option>
                </select>
            </div>
        </div>
        <div class="form-actions"><button class="btn" type="submit">Create invoice</button></div>
    </form>
    <?php endif; ?>
</div>

<div class="panel">
    <h2>Invoices</h2>
    <form method="get" class="toolbar">
        <input type="search" name="q" placeholder="Search by patient or description" value="<?php echo e($search); ?>">
        <select name="status" style="padding:10px;border:1px solid var(--line);border-radius:8px;font:inherit">
            <option value="">All</option>
            <option value="Paid" <?php echo $statusFilter === 'Paid' ? 'selected' : ''; ?>>Paid</option>
            <option value="Unpaid" <?php echo $statusFilter === 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
        </select>
        <button class="btn" type="submit">Filter</button>
        <?php if ($search !== '' || $statusFilter !== ''): ?><a href="billing.php" class="btn btn-ghost">Clear</a><?php endif; ?>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Invoice</th><th>Date</th><th>Patient</th><th>Description</th><th>Amount</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($bills)): ?>
                <tr><td colspan="7" class="empty">No invoices found.</td></tr>
            <?php else: foreach ($bills as $b): ?>
                <tr>
                    <td><a href="invoice.php?id=<?php echo (int)$b['id']; ?>" style="color:var(--brand);font-weight:600">INV-<?php echo str_pad($b['id'], 5, '0', STR_PAD_LEFT); ?></a></td>
                    <td><?php echo date('j M Y', strtotime($b['bill_date'])); ?></td>
                    <td><b><?php echo e($b['pname']); ?></b></td>
                    <td><?php echo e(mb_strimwidth($b['description'], 0, 40, '...')); ?></td>
                    <td><?php echo money($b['amount']); ?></td>
                    <td><?php echo badge($b['status']); ?></td>
                    <td>
                        <form method="post" class="row-form" style="justify-content:flex-end"
                              onsubmit="return !event.submitter || event.submitter.value !== 'delete' || confirm('Delete this invoice?');">
                            <input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>">
                            <input type="hidden" name="return" value="<?php echo e($returnQs); ?>">
                            <button class="btn btn-ghost btn-sm" type="submit" name="action" value="toggle">
                                <?php echo $b['status'] === 'Paid' ? 'Mark unpaid' : 'Mark paid'; ?>
                            </button>
                            <button class="btn btn-danger" type="submit" name="action" value="delete">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php echo pager($page, $totalPages, ['q' => $search, 'status' => $statusFilter]); ?>
</div>

</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
