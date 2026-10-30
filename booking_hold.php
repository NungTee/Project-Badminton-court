<?php
// booking_hold.php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/crud_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

verify_csrf();

$courtSlotId = valid_id($_POST['court_slot_id'] ?? null);
$userId = (int)($_SESSION['user_id'] ?? 0);

if (!$courtSlotId || !$userId) {
    page_start("เกิดข้อผิดพลาด");
    echo '<p class="err" style="color:#c62828; background:#ffebee; padding:10px; border-radius:4px;">ไม่พบข้อมูลสล็อตเวลาหรือข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง</p>';
    echo '<p><a href="index.php">กลับสู่หน้าหลัก</a></p>';
    page_end();
    exit;
}

// 1. เช็กว่าสล็อตนี้ยังว่างอยู่หรือไม่
$checkSql = "
    SELECT cs.id, cs.price 
    FROM court_slots cs
    LEFT JOIN slot_holds sh ON cs.id = sh.court_slot_id AND sh.expires_at > NOW()
    WHERE cs.id = ? AND cs.is_available = 1 AND sh.id IS NULL
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $checkSql);
mysqli_stmt_bind_param($stmt, "i", $courtSlotId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$slot = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$slot) {
    page_start("เกิดข้อผิดพลาด");
    echo '<p class="err" style="color:#c62828; background:#ffebee; padding:10px; border-radius:4px;">สล็อตเวลานี้ถูกจองหรือมีผู้ใช้อื่นกำลังทำรายการอยู่ กรุณาเลือกเวลาอื่น</p>';
    echo '<p><a href="index.php">กลับสู่หน้าหลัก</a></p>';
    page_end();
    exit;
}

// 2. บันทึกการ Hold ไว้ 5 นาที
$expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
$holdSql = "INSERT INTO slot_holds (court_slot_id, user_id, expires_at) VALUES (?, ?, ?)";
$stmt = mysqli_prepare($conn, $holdSql);
mysqli_stmt_bind_param($stmt, "iis", $courtSlotId, $userId, $expiresAt);

if (mysqli_stmt_execute($stmt)) {
    $holdId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    header('Location: payment.php?hold_id=' . $holdId);
    exit;
} else {
    mysqli_stmt_close($stmt);
    page_start("เกิดข้อผิดพลาด");
    echo '<p class="err" style="color:#c62828; background:#ffebee; padding:10px; border-radius:4px;">ไม่สามารถทำรายการล็อกเวลาได้ กรุณาลองใหม่อีกครั้ง</p>';
    echo '<p><a href="index.php">กลับสู่หน้าหลัก</a></p>';
    page_end();
    exit;
}