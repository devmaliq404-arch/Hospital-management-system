<?php
// Expects: $pageTitle (string), $active (string) set before including.
$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Guest';
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'Staff';
$pageTitle = isset($pageTitle) ? $pageTitle : 'HMS';
$active = isset($active) ? $active : '';

function navItem($key, $href, $label, $icon, $active) {
    $cls = ($key === $active) ? ' class="active"' : '';
    echo '<a href="' . $href . '"' . $cls . '><svg viewBox="0 0 24 24">' . $icon . '</svg>' . $label . '</a>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HMS | <?php echo htmlspecialchars($pageTitle); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root { --ink:#17283a; --muted:#64768a; --line:#e1e8ee; --bg:#f3f6f8; --card:#fff; --brand:#0d7c80; --brand-dark:#095b5e; --brand-soft:#dff1f1; --side:#12303f; --danger:#c0392b; --ok:#1f8a54; }
* { box-sizing:border-box; }
body { margin:0; font-family:'Figtree','Segoe UI',Arial,sans-serif; background:var(--bg); color:var(--ink); line-height:1.5; }
a { color:inherit; text-decoration:none; }
.sidebar { position:fixed; inset:0 auto 0 0; width:250px; background:var(--side); color:#cfdde6; padding:22px 16px; overflow-y:auto; z-index:20; transition:transform .25s ease; }
.brand { display:flex; align-items:center; gap:10px; padding:0 8px 22px; color:#fff; }
.brand-mark { width:38px; height:38px; border-radius:10px; background:var(--brand); display:grid; place-items:center; font-weight:700; font-size:22px; }
.brand b { font-size:18px; display:block; line-height:1.1; }
.brand small { color:#8fb0c0; font-size:12px; }
.menu-title { font-size:12px; color:#7f9fb0; padding:14px 10px 6px; }
.menu a { display:flex; align-items:center; gap:12px; padding:10px 12px; border-radius:8px; font-size:15px; margin-bottom:2px; }
.menu a:hover { background:rgba(255,255,255,.07); color:#fff; }
.menu a.active { background:var(--brand); color:#fff; }
.menu svg { width:18px; height:18px; flex:none; stroke:currentColor; fill:none; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round; }
.main { margin-left:250px; min-height:100vh; }
.topbar { display:flex; align-items:center; gap:16px; padding:14px 28px; background:var(--card); border-bottom:1px solid var(--line); position:sticky; top:0; z-index:10; }
.menu-toggle { display:none; background:none; border:1px solid var(--line); border-radius:8px; padding:6px 10px; font-size:18px; cursor:pointer; }
.top-title { font-weight:700; font-size:18px; }
.top-right { margin-left:auto; display:flex; align-items:center; gap:16px; }
.user { display:flex; align-items:center; gap:10px; }
.avatar { width:38px; height:38px; border-radius:50%; background:var(--brand-soft); color:var(--brand-dark); display:grid; place-items:center; font-weight:700; }
.user-info b { display:block; font-size:14px; line-height:1.2; }
.user-info small { color:var(--muted); font-size:12px; }
.logout-btn { background:var(--ink); color:#fff; padding:9px 16px; border-radius:8px; font-size:14px; font-weight:600; }
.logout-btn:hover { background:var(--danger); }
.content { padding:28px; max-width:1280px; }
.page-head { display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:12px; margin-bottom:22px; }
.page-head h1 { margin:0; font-size:28px; }
.page-head p { margin:2px 0 0; color:var(--muted); }
.panel { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:20px; }
.btn { display:inline-block; background:var(--brand); color:#fff; border:0; padding:10px 18px; border-radius:8px; font:inherit; font-weight:600; cursor:pointer; }
.btn:hover { background:var(--brand-dark); }
.btn-ghost { background:#fff; color:var(--ink); border:1px solid var(--line); }
.btn-ghost:hover { background:var(--bg); }
.btn-danger { background:#fff; color:var(--danger); border:1px solid #efc7c2; padding:6px 12px; font-size:13px; }
.btn-danger:hover { background:var(--danger); color:#fff; }
.toolbar { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; }
.toolbar input[type=search] { flex:1; min-width:220px; padding:10px 14px; border:1px solid var(--line); border-radius:8px; font:inherit; }
.table-wrap { overflow-x:auto; }
table { width:100%; border-collapse:collapse; font-size:14px; }
th { text-align:left; color:var(--muted); font-weight:600; padding:10px 12px; border-bottom:1px solid var(--line); white-space:nowrap; }
td { padding:12px; border-bottom:1px solid var(--line); white-space:nowrap; }
tr:last-child td { border-bottom:0; }
.empty { text-align:center; color:var(--muted); padding:34px 12px !important; white-space:normal; }
.badge { padding:4px 10px; border-radius:20px; font-size:12px; font-weight:600; background:var(--brand-soft); color:var(--brand-dark); }
.flash { padding:12px 16px; border-radius:8px; margin-bottom:18px; font-size:14px; background:#dcf3e7; color:var(--ok); border:1px solid #bfe5cf; }
.flash.error { background:#fbe4e1; color:var(--danger); border-color:#efc7c2; }
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.field { display:flex; flex-direction:column; gap:6px; }
.field.full { grid-column:1 / -1; }
.field label { font-size:14px; font-weight:600; }
.field input, .field select, .field textarea { padding:10px 12px; border:1px solid var(--line); border-radius:8px; font:inherit; background:#fff; }
.field input:focus, .field select:focus, .field textarea:focus { outline:2px solid var(--brand); }
.form-actions { display:flex; gap:10px; margin-top:20px; }
.pager { display:flex; gap:6px; margin-top:16px; flex-wrap:wrap; }
.pager a, .pager span { padding:6px 12px; border:1px solid var(--line); border-radius:6px; font-size:14px; }
.pager .current { background:var(--brand); color:#fff; border-color:var(--brand); }
@media (max-width:800px) {
  .sidebar { transform:translateX(-100%); } .sidebar.open { transform:none; }
  .main { margin-left:0; } .menu-toggle { display:block; } .user-info { display:none; }
  .content { padding:18px; } .topbar { padding:12px 16px; } .form-grid { grid-template-columns:1fr; }
}
</style>
</head>
<body>

<aside class="sidebar" id="sidebar">
    <div class="brand">
        <div class="brand-mark">+</div>
        <div><b>HMS</b><small>Hospital Management</small></div>
    </div>
    <nav class="menu">
        <?php
        navItem('dashboard', 'homepage.php', 'Dashboard', '<path d="M3 11l9-8 9 8v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>', $active);
        echo '<div class="menu-title">Patient care</div>';
        navItem('patients', 'patients.php', 'Patients', '<circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-7 7-7s7 3 7 7"/><path d="M17 11h5M19.5 8.5v5"/>', $active);
        navItem('appointments', 'appointments.php', 'Appointments', '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/>', $active);
        navItem('admissions', 'admissions.php', 'Admissions &amp; beds', '<path d="M3 18v-8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v8M3 18h18M3 21v-3M21 21v-3M7 8V6h5v2"/>', $active);
        navItem('records', 'records.php', 'Medical records', '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5M9 14h6M9 17h4"/>', $active);
        echo '<div class="menu-title">Clinical</div>';
        navItem('doctors', 'doctors.php', 'Doctors &amp; staff', '<circle cx="12" cy="7" r="4"/><path d="M5 21v-2a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v2"/>', $active);
        navItem('laboratory', 'laboratory.php', 'Laboratory', '<path d="M9 3h6M10 3v6l-5 9a2 2 0 0 0 2 3h10a2 2 0 0 0 2-3l-5-9V3"/>', $active);
        navItem('pharmacy', 'pharmacy.php', 'Pharmacy', '<rect x="3" y="9" width="18" height="6" rx="3" transform="rotate(-30 12 12)"/><path d="M10 7l4 7"/>', $active);
        echo '<div class="menu-title">Administration</div>';
        navItem('billing', 'billing.php', 'Billing', '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>', $active);
        navItem('reports', 'reports.php', 'Reports', '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>', $active);
        navItem('settings', 'settings.php', 'Settings', '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>', $active);
        ?>
    </nav>
</aside>

<div class="main">
    <header class="topbar">
        <button class="menu-toggle" id="menuToggle" aria-label="Open menu">&#9776;</button>
        <div class="top-title"><?php echo htmlspecialchars($pageTitle); ?></div>
        <div class="top-right">
            <div class="user">
                <div class="avatar"><?php echo htmlspecialchars(strtoupper(substr($name, 0, 1))); ?></div>
                <div class="user-info">
                    <b><?php echo htmlspecialchars($name); ?></b>
                    <small><?php echo htmlspecialchars($role); ?></small>
                </div>
            </div>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <main class="content">
    <?php
    if (!empty($_SESSION['flash'])) {
        $type = !empty($_SESSION['flash_type']) ? ' ' . $_SESSION['flash_type'] : '';
        echo '<div class="flash' . htmlspecialchars($type) . '">' . htmlspecialchars($_SESSION['flash']) . '</div>';
        unset($_SESSION['flash'], $_SESSION['flash_type']);
    }
    ?>
