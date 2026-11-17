<?php
session_start();
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/partials/helpers.php';

$categories = [
    'Analgesic', 'Antibiotic', 'Antimalarial', 'Antihypertensive', 'Antidiabetic',
    'Rehydration', 'Supplement', 'Antihistamine', 'Antacid', 'Other',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'add') {
        $name     = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $stock    = (int)($_POST['stock'] ?? 0);
        $price    = (float)($_POST['unit_price'] ?? 0);
        $reorder  = (int)($_POST['reorder_level'] ?? 10);
        $expiry   = trim($_POST['expiry_date'] ?? '');

        if ($name === '' || $stock < 0 || $price < 0 || $reorder < 0) {
            flash('Please enter a medication name and valid numbers.', 'error');
        } elseif ($expiry !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry)) {
            flash('Invalid expiry date.', 'error');
        } else {
            $expiryVal = $expiry === '' ? null : $expiry;
            $stmt = $conn->prepare(
                "INSERT INTO medications (name, category, stock, unit_price, reorder_level, expiry_date)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("ssidis", $name, $category, $stock, $price, $reorder, $expiryVal);
            $stmt->execute();
            $stmt->close();
            flash('Medication added.');
        }
    } elseif ($action === 'restock' || $action === 'dispense') {
        $qty = (int)($_POST['qty'] ?? 0);
        if ($qty < 1) {
            flash('Enter a quantity of 1 or more.', 'error');
        } elseif ($action === 'restock') {
            $stmt = $conn->prepare("UPDATE medications SET stock = stock + ? WHERE id = ?");
            $stmt->bind_param("ii", $qty, $id);
            $stmt->execute();
            $stmt->close();
            flash("Added $qty to stock.");
        } else {
            $stmt = $conn->prepare("UPDATE medications SET stock = stock - ? WHERE id = ? AND stock >= ?");
            $stmt->bind_param("iii", $qty, $id, $qty);
            $stmt->execute();
            $ok = $stmt->affected_rows === 1;
            $stmt->close();
            if ($ok) flash("Dispensed $qty.");
            else     flash('Not enough stock to dispense that quantity.', 'error');
        }
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM medications WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        flash('Medication removed.');
    }
    redirect('pharmacy.php' . (!empty($_POST['return']) ? '?' . ltrim($_POST['return'], '?') : ''));
}

$search = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? '';
if (!in_array($filter, ['low', 'expiring'], true)) $filter = '';
$like = '%' . $search . '%';

$extra = '';
if ($filter === 'low')      $extra = " AND stock <= reorder_level";
if ($filter === 'expiring') $extra = " AND expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)";

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset  = ($page - 1) * $perPage;

$stmt = $conn->prepare("SELECT COUNT(*) FROM medications WHERE (name LIKE ? OR category LIKE ?) $extra");
$stmt->bind_param("ss", $like, $like);
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();
$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = $conn->prepare(
    "SELECT id, name, category, stock, unit_price, reorder_level, expiry_date
     FROM medications WHERE (name LIKE ? OR category LIKE ?) $extra
     ORDER BY name ASC LIMIT ? OFFSET ?"
);
$stmt->bind_param("ssii", $like, $like, $perPage, $offset);
$stmt->execute();
$meds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalItems = (int)db_value($conn, "SELECT COUNT(*) FROM medications");
$lowCount   = (int)db_value($conn, "SELECT COUNT(*) FROM medications WHERE stock <= reorder_level");
$expCount   = (int)db_value($conn, "SELECT COUNT(*) FROM medications WHERE expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)");
$stockValue = (float)db_value($conn, "SELECT SUM(stock * unit_price) FROM medications");

$returnQs = http_build_query(array_filter(['q' => $search, 'filter' => $filter, 'page' => $page > 1 ? $page : null]));

$pageTitle = 'Pharmacy';
$active    = 'pharmacy';
include __DIR__ . '/partials/header.php';
?>

<div class="page-head">
    <div>
        <h1>Pharmacy</h1>
        <p>Medication stock, dispensing and expiry tracking.</p>
    </div>
</div>

<div class="cards">
    <div class="card-stat"><b><?php echo $totalItems; ?></b><span>Medications</span></div>
    <a href="pharmacy.php?filter=low" class="card-stat <?php echo $lowCount ? 'warn' : ''; ?>"><b><?php echo $lowCount; ?></b><span>Low stock</span></a>
    <a href="pharmacy.php?filter=expiring" class="card-stat <?php echo $expCount ? 'bad' : ''; ?>"><b><?php echo $expCount; ?></b><span>Expiring within 90 days</span></a>
    <div class="card-stat"><b><?php echo money($stockValue); ?></b><span>Stock value</span></div>
</div>

<div class="stack">

<div class="panel">
    <h2>Add medication</h2>
    <form method="post">
        <input type="hidden" name="action" value="add">
        <div class="form-grid">
            <div class="field">
                <label for="name">Name *</label>
                <input type="text" id="name" name="name" placeholder="e.g. Paracetamol 500mg" required>
            </div>
            <div class="field">
                <label for="category">Category</label>
                <input type="text" id="category" name="category" list="cats">
                <datalist id="cats"><?php foreach ($categories as $c): ?><option value="<?php echo e($c); ?>"><?php endforeach; ?></datalist>
            </div>
            <div class="field">
                <label for="stock">Quantity in stock *</label>
                <input type="number" id="stock" name="stock" min="0" value="0" required>
            </div>
            <div class="field">
                <label for="unit_price">Unit price (₦) *</label>
                <input type="number" id="unit_price" name="unit_price" min="0" step="0.01" value="0" required>
            </div>
            <div class="field">
                <label for="reorder_level">Reorder level</label>
                <input type="number" id="reorder_level" name="reorder_level" min="0" value="10">
            </div>
            <div class="field">
                <label for="expiry_date">Expiry date</label>
                <input type="date" id="expiry_date" name="expiry_date">
            </div>
        </div>
        <div class="form-actions"><button class="btn" type="submit">Add medication</button></div>
    </form>
</div>

<div class="panel">
    <h2>Inventory</h2>
    <form method="get" class="toolbar">
        <input type="search" name="q" placeholder="Search by name or category" value="<?php echo e($search); ?>">
        <input type="hidden" name="filter" value="<?php echo e($filter); ?>">
        <button class="btn" type="submit">Search</button>
        <?php if ($search !== '' || $filter !== ''): ?><a href="pharmacy.php" class="btn btn-ghost">Clear</a><?php endif; ?>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Medication</th><th>Category</th><th>Stock</th><th>Price</th><th>Expiry</th><th>Update stock</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($meds)): ?>
                <tr><td colspan="7" class="empty">No medications found.</td></tr>
            <?php else: foreach ($meds as $m):
                $low = $m['stock'] <= $m['reorder_level'];
                $expTs = $m['expiry_date'] ? strtotime($m['expiry_date']) : null;
                $expired = $expTs !== null && $expTs < strtotime(date('Y-m-d'));
                $soon = $expTs !== null && !$expired && $expTs <= strtotime('+90 days'); ?>
                <tr>
                    <td><b><?php echo e($m['name']); ?></b></td>
                    <td><?php echo e($m['category'] ?: '-'); ?></td>
                    <td class="<?php echo $low ? 'text-bad' : ''; ?>">
                        <?php echo (int)$m['stock']; ?><?php echo $low ? ' (low)' : ''; ?>
                    </td>
                    <td><?php echo money($m['unit_price']); ?></td>
                    <td class="<?php echo $expired ? 'text-bad' : ($soon ? 'text-warn' : ''); ?>">
                        <?php echo $expTs ? date('j M Y', $expTs) : '-'; ?><?php echo $expired ? ' (expired)' : ($soon ? ' (soon)' : ''); ?>
                    </td>
                    <td>
                        <form method="post" class="row-form">
                            <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
                            <input type="hidden" name="return" value="<?php echo e($returnQs); ?>">
                            <input type="number" name="qty" min="1" value="1" style="width:70px">
                            <button class="btn btn-sm" type="submit" name="action" value="restock">Restock</button>
                            <button class="btn btn-ghost btn-sm" type="submit" name="action" value="dispense">Dispense</button>
                        </form>
                    </td>
                    <td>
                        <form method="post" style="margin:0" onsubmit="return confirm('Remove this medication?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
                            <input type="hidden" name="return" value="<?php echo e($returnQs); ?>">
                            <button class="btn btn-danger" type="submit">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php echo pager($page, $totalPages, ['q' => $search, 'filter' => $filter]); ?>
</div>

</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
