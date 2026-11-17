<?php require_login(); $pageTitle = $pageTitle ?? 'Dashboard'; $flashMessage = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($pageTitle) ?> | CarePoint HMS</title><link rel="stylesheet" href="assets/style.css"></head><body>
<div class="app-shell"><aside class="sidebar"><a class="brand" href="index.php"><span class="brand-mark">✚</span><span>CarePoint<small>HOSPITAL SYSTEM</small></span></a><div class="nav-label">WORKSPACE</div><nav>
<a href="index.php" class="<?= basename($_SERVER['PHP_SELF'])==='index.php'?'active':'' ?>">▦ <span>Dashboard</span></a>
<a href="patients.php" class="<?= basename($_SERVER['PHP_SELF'])==='patients.php'?'active':'' ?>">♙ <span>Patients</span></a>
<a href="doctors.php" class="<?= basename($_SERVER['PHP_SELF'])==='doctors.php'?'active':'' ?>">⚕ <span>Doctors</span></a>
<a href="appointments.php" class="<?= basename($_SERVER['PHP_SELF'])==='appointments.php'?'active':'' ?>">▣ <span>Appointments</span></a>
<a href="records.php" class="<?= basename($_SERVER['PHP_SELF'])==='records.php'?'active':'' ?>">▤ <span>Medical records</span></a>
<a href="billing.php" class="<?= basename($_SERVER['PHP_SELF'])==='billing.php'?'active':'' ?>">＄ <span>Billing</span></a>
</nav><div class="sidebar-bottom"><div class="user-avatar"><?= e(strtoupper(substr($_SESSION['user_name'] ?? 'U',0,1))) ?></div><div class="user-meta"><strong><?= e($_SESSION['user_name'] ?? 'User') ?></strong><small><?= e(ucfirst($_SESSION['user_role'] ?? 'staff')) ?></small></div><a class="logout" href="logout.php" title="Sign out">↗</a></div></aside>
<main class="main-area"><header class="topbar"><div><span class="eyebrow">CAREPOINT / MANAGEMENT</span><h1><?= e($pageTitle) ?></h1></div><div class="topbar-right"><span class="status-dot"></span><span>System workspace</span><span class="top-date"><?= date('D, M j, Y') ?></span></div></header><section class="page-content">
<?php if ($flashMessage): ?><div class="alert <?= e($flashMessage['type']) ?>"><?= e($flashMessage['message']) ?></div><?php endif; ?>
