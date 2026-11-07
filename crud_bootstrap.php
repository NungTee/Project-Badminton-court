<?php
// crud_bootstrap.php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
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
    $currentPage = basename($_SERVER['PHP_SELF']);
    echo '<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>' . h($title) . ' | BADMINTON COURT</title>
    <!-- เติม timestamp ท้ายไฟล์ CSS บังคับอัปเดตแคชทันที -->
    <link rel="stylesheet" href="assets/css/style.css?v=' . time() . '">
</head>
<body>
<main class="wrap">
    <nav class="navbar">
        <a href="index.php" class="logo">🏸 BADMINTON COURT</a>
        <div class="nav-links">
            <a href="index.php" class="nav-item ' . ($currentPage === 'index.php' ? 'active' : '') . '">จองสนาม</a>
            <a href="shop.php" class="nav-item ' . ($currentPage === 'shop.php' ? 'active' : '') . '">🛒 ร้านค้า</a>
            <a href="resale_market.php" class="nav-item ' . ($currentPage === 'resale_market.php' ? 'active' : '') . '">ตลาดคอร์ทหลุด</a>
            <a href="my_bookings.php" class="nav-item ' . ($currentPage === 'my_bookings.php' ? 'active' : '') . '">รายการจองของฉัน</a>';
            
            if (($_SESSION['role'] ?? '') === 'OWNER') {
                echo '<a href="admin_shop.php" class="nav-item admin-link ' . ($currentPage === 'admin_shop.php' ? 'active' : '') . '">🛍️ จัดการร้านค้า</a>';
                echo '<a href="admin_slots.php" class="nav-item admin-link ' . ($currentPage === 'admin_slots.php' ? 'active' : '') . '">⚙️ จัดการเวลา</a>';
                echo '<a href="admin_dashboard.php" class="nav-item admin-link ' . ($currentPage === 'admin_dashboard.php' ? 'active' : '') . '">📊 แดชบอร์ด</a>';
            }
            
    echo '  <a href="logout.php" class="nav-item logout-link">ออกจากระบบ →</a>
        </div>
    </nav>
    <div class="panel">
        <h1>' . h($title) . '</h1>';
}

function page_end() {
    echo '</div></main></body></html>';
}