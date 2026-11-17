<?php
session_start();

require_once("connect.php"); // must create $conn (mysqli)

$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Guest';
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'Staff';

/* ------------------------------------------------------------
   Helpers: if a table doesn't exist yet, the page still loads
   and shows demo data instead of crashing.
   Expected tables (rename to match yours):
   patients(id, name, age, gender, created_at)
   doctors(id, name, specialty, status)
   appointments(id, patient_name, doctor_name, department,
                appt_time, status, appt_date)
   beds(id, ward, status)  -- status: 'occupied' or 'available'
------------------------------------------------------------ */
function getCount($conn, $sql, $fallback = 0) {
    if (!isset($conn)) return $fallback;
    try {
        $res = @mysqli_query($conn, $sql);
        if ($res) {
            $row = mysqli_fetch_row($res);
            return (int)$row[0];
        }
    } catch (Throwable $e) {}
    return $fallback;
}

function getRows($conn, $sql, $fallback = []) {
    if (!isset($conn)) return $fallback;
    try {
        $res = @mysqli_query($conn, $sql);
        if ($res && mysqli_num_rows($res) > 0) {
            return mysqli_fetch_all($res, MYSQLI_ASSOC);
        }
    } catch (Throwable $e) {}
    return $fallback;
}

$totalPatients  = getCount($conn, "SELECT COUNT(*) FROM patients", 1248);
$totalDoctors   = getCount($conn, "SELECT COUNT(*) FROM doctors", 36);
$todayAppts     = getCount($conn, "SELECT COUNT(*) FROM appointments WHERE appt_date = CURDATE()", 42);
$bedsOccupied   = getCount($conn, "SELECT COUNT(*) FROM beds WHERE status='occupied'", 68);
$bedsTotal      = getCount($conn, "SELECT COUNT(*) FROM beds", 90);
$bedPercent     = $bedsTotal > 0 ? round(($bedsOccupied / $bedsTotal) * 100) : 0;

$appointments = getRows($conn,
    "SELECT patient_name, doctor_name, department, appt_time, status
     FROM appointments WHERE appt_date = CURDATE()
     ORDER BY appt_time ASC LIMIT 6",
    [
        ['patient_name' => 'Adebayo Johnson', 'doctor_name' => 'Dr. Okafor',  'department' => 'Cardiology', 'appt_time' => '09:00 AM', 'status' => 'Completed'],
        ['patient_name' => 'Funke Alabi',     'doctor_name' => 'Dr. Bello',   'department' => 'Pediatrics', 'appt_time' => '10:30 AM', 'status' => 'In progress'],
        ['patient_name' => 'Chinedu Eze',     'doctor_name' => 'Dr. Adeyemi', 'department' => 'Orthopedics','appt_time' => '11:15 AM', 'status' => 'Waiting'],
        ['patient_name' => 'Zainab Musa',     'doctor_name' => 'Dr. Ibrahim', 'department' => 'Neurology',  'appt_time' => '01:00 PM', 'status' => 'Scheduled'],
        ['patient_name' => 'Tunde Bakare',    'doctor_name' => 'Dr. Okafor',  'department' => 'Cardiology', 'appt_time' => '02:30 PM', 'status' => 'Scheduled'],
    ]
);

$recentPatients = getRows($conn,
    "SELECT name, age, gender, created_at FROM patients ORDER BY id DESC LIMIT 5",
    [
        ['name' => 'Ngozi Obi',      'age' => 34, 'gender' => 'Female', 'created_at' => 'Today'],
        ['name' => 'Samuel Ojo',     'age' => 58, 'gender' => 'Male',   'created_at' => 'Today'],
        ['name' => 'Halima Yusuf',   'age' => 7,  'gender' => 'Female', 'created_at' => 'Yesterday'],
        ['name' => 'Peter Okonkwo',  'age' => 45, 'gender' => 'Male',   'created_at' => 'Yesterday'],
    ]
);

$departments = [
    ['Emergency', 12, 15], ['Cardiology', 14, 20], ['Pediatrics', 9, 18],
    ['Maternity', 16, 20], ['Surgery', 11, 14],
];

$alerts = [
    ['Low stock: Paracetamol 500mg (18 packs left)', 'warn'],
    ['Lab results ready for 5 patients', 'info'],
    ['Ward B oxygen supply check due at 4:00 PM', 'warn'],
    ['3 new patient registrations pending review', 'info'],
];

function statusClass($s) {
    $s = strtolower($s);
    if ($s === 'completed')   return 'st-done';
    if ($s === 'in progress') return 'st-live';
    if ($s === 'waiting')     return 'st-wait';
    if ($s === 'cancelled')   return 'st-cancel';
    return 'st-sched';
}

$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iconic Care hospital</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --ink: #17283a;
    --muted: #64768a;
    --line: #e1e8ee;
    --bg: #f3f6f8;
    --card: #ffffff;
    --brand: #0d7c80;
    --brand-dark: #095b5e;
    --brand-soft: #dff1f1;
    --side: #12303f;
    --danger: #c0392b;
    --warn: #b7791f;
    --ok: #1f8a54;
}
* { box-sizing: border-box; }
body {
    margin: 0;
    font-family: 'Figtree', 'Segoe UI', Arial, sans-serif;
    background: var(--bg);
    color: var(--ink);
    line-height: 1.5;
}
a { color: inherit; text-decoration: none; }

/* ---------- Sidebar ---------- */
.sidebar {
    position: fixed; inset: 0 auto 0 0;
    width: 250px;
    background: var(--side);
    color: #cfdde6;
    padding: 22px 16px;
    overflow-y: auto;
    z-index: 20;
    transition: transform .25s ease;
}
.brand { display: flex; align-items: center; gap: 10px; padding: 0 8px 22px; color: #fff; }
.brand-mark {
    width: 38px; height: 38px; border-radius: 10px;
    background: var(--brand);
    display: grid; place-items: center;
    font-weight: 700; font-size: 22px;
}
.brand b { font-size: 18px; display: block; line-height: 1.1; }
.brand small { color: #8fb0c0; font-size: 12px; }
.menu-title { font-size: 12px; color: #7f9fb0; padding: 14px 10px 6px; }
.menu a {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 12px; border-radius: 8px;
    font-size: 15px; margin-bottom: 2px;
}
.menu a:hover { background: rgba(255,255,255,.07); color: #fff; }
.menu a.active { background: var(--brand); color: #fff; }
.menu svg { width: 18px; height: 18px; flex: none; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

/* ---------- Main ---------- */
.main { margin-left: 250px; min-height: 100vh; }
.topbar {
    display: flex; align-items: center; gap: 16px;
    padding: 14px 28px;
    background: var(--card);
    border-bottom: 1px solid var(--line);
    position: sticky; top: 0; z-index: 10;
}
.menu-toggle { display: none; background: none; border: 1px solid var(--line); border-radius: 8px; padding: 6px 10px; font-size: 18px; cursor: pointer; }
.search { flex: 1; max-width: 420px; }
.search input {
    width: 100%; padding: 10px 14px;
    border: 1px solid var(--line); border-radius: 8px;
    font: inherit; background: var(--bg);
}
.search input:focus { outline: 2px solid var(--brand); background: #fff; }
.top-right { margin-left: auto; display: flex; align-items: center; gap: 16px; }
.bell { position: relative; background: none; border: 0; cursor: pointer; padding: 4px; }
.bell svg { width: 22px; height: 22px; stroke: var(--ink); fill: none; stroke-width: 1.8; }
.bell .dot { position: absolute; top: 2px; right: 3px; width: 9px; height: 9px; background: var(--danger); border-radius: 50%; border: 2px solid #fff; }
.user { display: flex; align-items: center; gap: 10px; }
.avatar {
    width: 38px; height: 38px; border-radius: 50%;
    background: var(--brand-soft); color: var(--brand-dark);
    display: grid; place-items: center; font-weight: 700;
}
.user-info b { display: block; font-size: 14px; line-height: 1.2; }
.user-info small { color: var(--muted); font-size: 12px; }
.logout-btn {
    background: var(--ink); color: #fff;
    padding: 9px 16px; border-radius: 8px;
    font-size: 14px; font-weight: 600;
}
.logout-btn:hover { background: var(--danger); }

.content { padding: 28px; max-width: 1280px; }
.page-head { display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 12px; margin-bottom: 22px; }
.page-head h1 { margin: 0; font-size: 28px; }
.page-head p { margin: 2px 0 0; color: var(--muted); }
.date-chip { background: var(--card); border: 1px solid var(--line); padding: 8px 14px; border-radius: 8px; font-size: 14px; color: var(--muted); }

/* ---------- Stat cards ---------- */
.stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 22px; }
.stat { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 18px; display: flex; gap: 14px; align-items: center; }
.stat-icon { width: 48px; height: 48px; border-radius: 12px; display: grid; place-items: center; flex: none; }
.stat-icon svg { width: 24px; height: 24px; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.ic-teal   { background: #dff1f1; color: #0d7c80; }
.ic-blue   { background: #e3edfb; color: #2a5db0; }
.ic-amber  { background: #fbf0d9; color: #a86b0a; }
.ic-rose   { background: #fbe4e1; color: #b8402f; }
.stat b { font-size: 28px; line-height: 1.1; display: block; }
.stat span { color: var(--muted); font-size: 14px; }

/* ---------- Grid & panels ---------- */
.grid { display: grid; grid-template-columns: 2fr 1fr; gap: 18px; margin-bottom: 18px; }
.panel { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 20px; }
.panel-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
.panel-head h2 { margin: 0; font-size: 18px; }
.panel-head a { color: var(--brand); font-size: 14px; font-weight: 600; }

.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 14px; }
th { text-align: left; color: var(--muted); font-weight: 600; padding: 10px 12px; border-bottom: 1px solid var(--line); white-space: nowrap; }
td { padding: 12px; border-bottom: 1px solid var(--line); white-space: nowrap; }
tr:last-child td { border-bottom: 0; }
.badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.st-done   { background: #dcf3e7; color: var(--ok); }
.st-live   { background: #dff1f1; color: var(--brand-dark); }
.st-wait   { background: #fbf0d9; color: var(--warn); }
.st-sched  { background: #e8edf2; color: var(--muted); }
.st-cancel { background: #fbe4e1; color: var(--danger); }

/* ---------- Quick actions ---------- */
.actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.action {
    display: flex; flex-direction: column; gap: 8px;
    padding: 14px; border: 1px solid var(--line); border-radius: 10px;
    font-weight: 600; font-size: 14px;
}
.action:hover { border-color: var(--brand); background: var(--brand-soft); }
.action svg { width: 22px; height: 22px; stroke: var(--brand); fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

/* ---------- Bed ring & departments ---------- */
.bed-wrap { display: flex; align-items: center; gap: 20px; }
.ring {
    --p: <?php echo $bedPercent; ?>;
    width: 110px; height: 110px; border-radius: 50%;
    background: conic-gradient(var(--brand) calc(var(--p) * 1%), #e5ecf0 0);
    display: grid; place-items: center; flex: none;
}
.ring div { width: 82px; height: 82px; background: #fff; border-radius: 50%; display: grid; place-items: center; font-size: 22px; font-weight: 700; }
.bed-text small { color: var(--muted); display: block; }
.bed-text b { font-size: 18px; }

.dept { margin-bottom: 14px; }
.dept:last-child { margin-bottom: 0; }
.dept-top { display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 5px; }
.dept-top span { color: var(--muted); }
.bar { height: 8px; background: #e5ecf0; border-radius: 8px; overflow: hidden; }
.bar i { display: block; height: 100%; background: var(--brand); border-radius: 8px; }
.bar i.high { background: var(--danger); }

/* ---------- Lists ---------- */
.list { list-style: none; margin: 0; padding: 0; }
.list li { display: flex; align-items: center; gap: 12px; padding: 11px 0; border-bottom: 1px solid var(--line); font-size: 14px; }
.list li:last-child { border-bottom: 0; }
.list small { color: var(--muted); display: block; }
.list .when { margin-left: auto; color: var(--muted); font-size: 13px; }
.pip { width: 10px; height: 10px; border-radius: 50%; flex: none; }
.pip.warn { background: var(--warn); }
.pip.info { background: var(--brand); }

.footer { text-align: center; color: var(--muted); font-size: 13px; padding: 22px; }

/* ---------- Responsive ---------- */
@media (max-width: 1100px) {
    .stats { grid-template-columns: repeat(2, 1fr); }
    .grid { grid-template-columns: 1fr; }
}
@media (max-width: 800px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.open { transform: none; }
    .main { margin-left: 0; }
    .menu-toggle { display: block; }
    .user-info { display: none; }
    .content { padding: 18px; }
    .topbar { padding: 12px 16px; }
}
@media (max-width: 520px) {
    .stats { grid-template-columns: 1fr; }
    .search { display: none; }
}
</style>
</head>
<body>

<!-- ============ SIDEBAR ============ -->
<aside class="sidebar" id="sidebar">
    <div class="brand">
        <div class="brand-mark">+</div>
        <div><b>Iconic Care Hospital</b><small>Hospital Management</small></div>
    </div>

    <nav class="menu">
        <a href="homepage.php" class="active">
            <svg viewBox="0 0 24 24"><path d="M3 11l9-8 9 8v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></svg>Dashboard
        </a>

        <div class="menu-title">Patient care</div>
        <a href="patients.php"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-7 7-7s7 3 7 7"/><path d="M17 11h5M19.5 8.5v5"/></svg>Patients</a>
        <a href="appointments.php"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/></svg>Appointments</a>
        <a href="admissions.php"><svg viewBox="0 0 24 24"><path d="M3 18v-8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v8M3 18h18M3 21v-3M21 21v-3M7 8V6h5v2"/></svg>Admissions &amp; beds</a>
        <a href="records.php"><svg viewBox="0 0 24 24"><path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5M9 14h6M9 17h4"/></svg>Medical records</a>

        <div class="menu-title">Clinical</div>
        <a href="doctors.php"><svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M5 21v-2a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v2"/></svg>Doctors &amp; staff</a>
        <a href="laboratory.php"><svg viewBox="0 0 24 24"><path d="M9 3h6M10 3v6l-5 9a2 2 0 0 0 2 3h10a2 2 0 0 0 2-3l-5-9V3"/></svg>Laboratory</a>
        <a href="pharmacy.php"><svg viewBox="0 0 24 24"><rect x="3" y="9" width="18" height="6" rx="3" transform="rotate(-30 12 12)"/><path d="M10 7l4 7"/></svg>Pharmacy</a>

        <div class="menu-title">Administration</div>
        <a href="billing.php"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/></svg>Billing</a>
        <a href="reports.php"><svg viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>Reports</a>
        <a href="settings.php"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1.3l2-1.5-2-3.4-2.3 1a7 7 0 0 0-2.2-1.3L14 3h-4l-.4 2.5a7 7 0 0 0-2.2 1.3l-2.3-1-2 3.4 2 1.5A7 7 0 0 0 5 12c0 .4 0 .9.1 1.3l-2 1.5 2 3.4 2.3-1a7 7 0 0 0 2.2 1.3L10 21h4l.4-2.5a7 7 0 0 0 2.2-1.3l2.3 1 2-3.4-2-1.5c.1-.4.1-.9.1-1.3z"/></svg>Settings</a>
    </nav>
</aside>

<!-- ============ MAIN ============ -->
<div class="main">
    <header class="topbar">
        <button class="menu-toggle" id="menuToggle" aria-label="Open menu">&#9776;</button>
        <div class="search">
            <input type="search" placeholder="Search patients, doctors or records">
        </div>
        <div class="top-right">
            <button class="bell" aria-label="Notifications">
                <svg viewBox="0 0 24 24"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 8 3 8H3s3-1 3-8M10 21a2 2 0 0 0 4 0"/></svg>
                <span class="dot"></span>
            </button>
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
        <div class="page-head">
            <div>
                <h1><?php echo $greeting; ?>, <?php echo htmlspecialchars($name); ?></h1>
                <p>Here is what is happening in the hospital today.</p>
            </div>
            <div class="date-chip"><?php echo date('l, j F Y'); ?></div>
        </div>

        <!-- Stat cards -->
        <section class="stats">
            <div class="stat">
                <div class="stat-icon ic-teal"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-7 7-7s7 3 7 7M17 4a4 4 0 0 1 0 8M22 21c0-3-2-5-5-6"/></svg></div>
                <div><b><?php echo number_format($totalPatients); ?></b><span>Total patients</span></div>
            </div>
            <div class="stat">
                <div class="stat-icon ic-blue"><svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M5 21v-2a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v2"/></svg></div>
                <div><b><?php echo number_format($totalDoctors); ?></b><span>Doctors on staff</span></div>
            </div>
            <div class="stat">
                <div class="stat-icon ic-amber"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/></svg></div>
                <div><b><?php echo number_format($todayAppts); ?></b><span>Appointments today</span></div>
            </div>
            <div class="stat">
                <div class="stat-icon ic-rose"><svg viewBox="0 0 24 24"><path d="M3 18v-8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v8M3 18h18M3 21v-3M21 21v-3"/></svg></div>
                <div><b><?php echo $bedsOccupied; ?>/<?php echo $bedsTotal; ?></b><span>Beds occupied</span></div>
            </div>
        </section>

        <!-- Appointments + Quick actions -->
        <section class="grid">
            <div class="panel">
                <div class="panel-head">
                    <h2>Today's appointments</h2>
                    <a href="appointments.php">View all</a>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>Patient</th><th>Doctor</th><th>Department</th><th>Time</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($appointments as $a): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($a['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($a['doctor_name']); ?></td>
                                <td><?php echo htmlspecialchars($a['department']); ?></td>
                                <td><?php echo htmlspecialchars($a['appt_time']); ?></td>
                                <td><span class="badge <?php echo statusClass($a['status']); ?>"><?php echo htmlspecialchars($a['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><h2>Quick actions</h2></div>
                <div class="actions">
                    <a class="action" href="add_patient.php"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-7 7-7s7 3 7 7M19 8v6M16 11h6"/></svg>Register patient</a>
                    <a class="action" href="book_appointment.php"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4M16 2v4M3 10h18M12 13v5M9.5 15.5h5"/></svg>Book appointment</a>
                    <a class="action" href="admit_patient.php"><svg viewBox="0 0 24 24"><path d="M3 18v-8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v8M3 18h18"/></svg>Admit patient</a>
                    <a class="action" href="create_bill.php"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>Create invoice</a>
                </div>
            </div>
        </section>

        <!-- Beds + Departments + Alerts -->
        <section class="grid" style="grid-template-columns: 1fr 1fr 1fr;">
            <div class="panel">
                <div class="panel-head"><h2>Bed occupancy</h2></div>
                <div class="bed-wrap">
                    <div class="ring"><div><?php echo $bedPercent; ?>%</div></div>
                    <div class="bed-text">
                        <b><?php echo $bedsOccupied; ?> occupied</b>
                        <small><?php echo max(0, $bedsTotal - $bedsOccupied); ?> beds available</small>
                        <small>Across <?php echo count($departments); ?> wards</small>
                    </div>
                </div>
                <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
                <?php foreach ($departments as $d):
                    $pct = round(($d[1] / $d[2]) * 100); ?>
                    <div class="dept">
                        <div class="dept-top"><?php echo htmlspecialchars($d[0]); ?><span><?php echo $d[1]; ?>/<?php echo $d[2]; ?> beds</span></div>
                        <div class="bar"><i class="<?php echo $pct >= 85 ? 'high' : ''; ?>" style="width:<?php echo $pct; ?>%"></i></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2>Recent patients</h2>
                    <a href="patients.php">View all</a>
                </div>
                <ul class="list">
                    <?php foreach ($recentPatients as $p): ?>
                    <li>
                        <div class="avatar" style="width:34px;height:34px;font-size:14px"><?php echo htmlspecialchars(strtoupper(substr($p['name'], 0, 1))); ?></div>
                        <div>
                            <?php echo htmlspecialchars($p['name']); ?>
                            <small><?php echo (int)$p['age']; ?> yrs, <?php echo htmlspecialchars($p['gender']); ?></small>
                        </div>
                        <span class="when"><?php echo htmlspecialchars($p['created_at']); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="panel">
                <div class="panel-head"><h2>Alerts</h2></div>
                <ul class="list">
                    <?php foreach ($alerts as $al): ?>
                    <li><span class="pip <?php echo $al[1]; ?>"></span><?php echo htmlspecialchars($al[0]); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

        <div class="footer">&copy; <?php echo date('Y'); ?> HMS Hospital Management System</div>
    </main>
</div>

<script>
document.getElementById('menuToggle').addEventListener('click', function () {
    document.getElementById('sidebar').classList.toggle('open');
});
</script>
</body>
</html>
