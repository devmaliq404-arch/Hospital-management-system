<?php
session_start();
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/partials/helpers.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare(
    "SELECT b.id, b.description, b.amount, b.status, b.bill_date,
            p.name AS pname, p.patient_code, p.phone, p.address
     FROM bills b LEFT JOIN patients p ON p.id = b.patient_id
     WHERE b.id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$bill = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$bill) {
    http_response_code(404);
    exit('Invoice not found. <a href="billing.php">Back to billing</a>');
}

$hospital = get_setting($conn, 'hospital_name', 'HMS Hospital');
$phone    = get_setting($conn, 'hospital_phone');
$email    = get_setting($conn, 'hospital_email');
$address  = get_setting($conn, 'hospital_address');
$number   = 'INV-' . str_pad($bill['id'], 5, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e($number); ?> | Invoice</title>
<style>
body { font-family: 'Segoe UI', Arial, sans-serif; background:#f3f6f8; color:#17283a; margin:0; padding:30px 16px; }
.sheet { max-width:760px; margin:0 auto; background:#fff; border:1px solid #e1e8ee; border-radius:12px; padding:40px; }
.top { display:flex; justify-content:space-between; gap:20px; flex-wrap:wrap; border-bottom:2px solid #0d7c80; padding-bottom:18px; margin-bottom:24px; }
h1 { margin:0; font-size:26px; color:#0d7c80; }
.muted { color:#64768a; font-size:14px; }
.meta { text-align:right; }
.meta b { font-size:20px; display:block; }
table { width:100%; border-collapse:collapse; margin:22px 0; }
th { text-align:left; background:#f3f6f8; padding:10px 12px; font-size:14px; }
td { padding:12px; border-bottom:1px solid #e1e8ee; }
.total { text-align:right; font-size:20px; font-weight:700; }
.stamp { display:inline-block; padding:4px 14px; border-radius:20px; font-weight:700; font-size:13px; }
.paid { background:#dcf3e7; color:#1f8a54; }
.unpaid { background:#fbf0d9; color:#b7791f; }
.actions { max-width:760px; margin:0 auto 16px; display:flex; gap:10px; }
.actions a, .actions button { padding:9px 16px; border-radius:8px; border:1px solid #e1e8ee; background:#fff; font:inherit; cursor:pointer; text-decoration:none; color:#17283a; }
.actions button { background:#0d7c80; color:#fff; border-color:#0d7c80; }
@media print { body { background:#fff; padding:0; } .actions { display:none; } .sheet { border:0; } }
</style>
</head>
<body>

<div class="actions">
    <a href="billing.php">&larr; Back to billing</a>
    <button onclick="window.print()">Print invoice</button>
</div>

<div class="sheet">
    <div class="top">
        <div>
            <h1><?php echo e($hospital); ?></h1>
            <?php if ($address): ?><div class="muted"><?php echo e($address); ?></div><?php endif; ?>
            <?php if ($phone): ?><div class="muted"><?php echo e($phone); ?></div><?php endif; ?>
            <?php if ($email): ?><div class="muted"><?php echo e($email); ?></div><?php endif; ?>
        </div>
        <div class="meta">
            <b><?php echo e($number); ?></b>
            <div class="muted">Date: <?php echo date('j F Y', strtotime($bill['bill_date'])); ?></div>
            <div style="margin-top:8px"><span class="stamp <?php echo $bill['status'] === 'Paid' ? 'paid' : 'unpaid'; ?>"><?php echo e(strtoupper($bill['status'])); ?></span></div>
        </div>
    </div>

    <div class="muted">Billed to</div>
    <div style="font-size:18px;font-weight:600"><?php echo e($bill['pname'] ?: '(deleted patient)'); ?></div>
    <?php if ($bill['patient_code']): ?><div class="muted">Patient ID: <?php echo e($bill['patient_code']); ?></div><?php endif; ?>
    <?php if ($bill['phone']): ?><div class="muted"><?php echo e($bill['phone']); ?></div><?php endif; ?>

    <table>
        <thead><tr><th>Description</th><th style="text-align:right">Amount</th></tr></thead>
        <tbody>
            <tr><td><?php echo e($bill['description']); ?></td><td style="text-align:right"><?php echo money($bill['amount']); ?></td></tr>
        </tbody>
    </table>

    <div class="total">Total: <?php echo money($bill['amount']); ?></div>
    <p class="muted" style="margin-top:30px">Thank you. Get well soon.</p>
</div>

</body>
</html>
