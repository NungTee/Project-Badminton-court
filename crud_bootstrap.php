<?php
// crud_bootstrap.php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['csrf'])) {$_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function csrf_field() {
    echo '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">';
}

function verify_csrf() {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
}

function valid_id($value) {
    $n = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $n === false ? null : $n;
}

function page_start($title) {
    echo '<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>' . h($title) . ' | Court Booking</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<main class="wrap">
    <nav class="navbar" style="display:flex; justify-content:space-between; align-items:center; background:#135c9a; color:white; padding:15px 20px; border-radius:8px; margin-bottom:20px;">
        <a href="index.php" class="logo" style="color:white; text-decoration:none; font-weight:bold; font-size:1.2rem;">🏸 Badminton Court</a>
        <div class="nav-links" style="display:flex; gap:15px; align-items:center;">
            <a href="index.php" style="color:white; text-decoration:none;">จองสนาม</a>
            <a href="resale_market.php" style="color:white; text-decoration:none;">ตลาดคอร์ทหลุด</a>
            <a href="my_bookings.php" style="color:white; text-decoration:none;">รายการจองของฉัน</a>';
            
            // แสดงเมนูแอดมินเฉพาะสิทธิ์ OWNER
            if (($_SESSION['role'] ?? '') === 'OWNER') {
                echo '<a href="admin_slots.php" style="color:#ffeb3b; font-weight:bold; text-decoration:none;">⚙️ จัดการแอดมิน</a>';
            }

    echo '  <a href="logout.php" class="btn-logout" style="color:#ffcdd2; text-decoration:none;">ออกจากระบบ</a>
        </div>
    </nav>
    <div class="panel">
        <h1>' . h($title) . '</h1>';
}

function page_end() {
    echo '</div></main></body></html>';
}