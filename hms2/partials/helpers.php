<?php
// Small helpers shared by the HMS pages.

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function flash($msg, $type = '') {
    $_SESSION['flash'] = $msg;
    $_SESSION['flash_type'] = $type;   // '' for success, 'error' for errors
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function money($n) {
    return '₦' . number_format((float)$n, 2);
}

// Run a SELECT and return all rows (empty array if the query fails)
function db_rows($conn, $sql) {
    try {
        $res = @$conn->query($sql);
        if ($res instanceof mysqli_result) {
            return $res->fetch_all(MYSQLI_ASSOC);
        }
    } catch (Throwable $t) {}
    return [];
}

// Run a SELECT and return the first column of the first row
function db_value($conn, $sql, $default = 0) {
    $rows = db_rows($conn, $sql);
    if ($rows) {
        $v = array_values($rows[0])[0];
        return $v === null ? $default : $v;
    }
    return $default;
}

function get_setting($conn, $key, $default = '') {
    try {
        $stmt = @$conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        if ($stmt) {
            $stmt->bind_param("s", $key);
            $stmt->execute();
            $stmt->bind_result($val);
            $found = $stmt->fetch();
            $stmt->close();
            if ($found && $val !== null && $val !== '') return $val;
        }
    } catch (Throwable $t) {}
    return $default;
}

// Coloured status pill
function badge($status) {
    $map = [
        'completed' => 'st-done', 'paid' => 'st-done', 'active' => 'st-done', 'discharged' => 'st-sched',
        'in progress' => 'st-live', 'admitted' => 'st-live',
        'pending' => 'st-wait', 'waiting' => 'st-wait', 'unpaid' => 'st-wait', 'on leave' => 'st-wait',
        'cancelled' => 'st-cancel',
    ];
    $cls = $map[strtolower($status)] ?? 'st-sched';
    return '<span class="badge ' . $cls . '">' . e($status) . '</span>';
}

// Page number links that keep the other query-string filters
function pager($page, $totalPages, $params = []) {
    if ($totalPages <= 1) return '';
    $out = '<div class="pager">';
    for ($i = 1; $i <= $totalPages; $i++) {
        $p = array_merge($params, ['page' => $i]);
        $p = array_filter($p, function ($v) { return $v !== '' && $v !== null; });
        $qs = http_build_query($p);
        $out .= ($i === $page)
            ? '<span class="current">' . $i . '</span>'
            : '<a href="?' . e($qs) . '">' . $i . '</a>';
    }
    return $out . '</div>';
}
