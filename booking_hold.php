<?php
// booking_hold.php
require_once __DIR__ . '/crud_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

verify_csrf();

$courtSlotId = valid_id($_POST['court_slot_id'] ?? null);
$userId = (int)$_SESSION['user_id'];

if (!$courtSlotId) {
    http_response_code(400);
    exit('Invalid Slot ID');
}

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
    echo '<p class="err">สล็อตเวลานี้ถูกจองหรือมีผู้ใช้อื่นกำลังทำรายการอยู่ กรุณาเลือกเวลาอื่น</p>';
    echo '<p><a href="index.php">กลับสู่หน้าหลัก</a></p>';
    page_end();
    exit;
}

$expiresAt = date('Y-m-d H:i:s', strtotime('+3 minutes'));
$holdSql = "INSERT INTO slot_holds (court_slot_id, user_id, expires_at) VALUES (?, ?, ?)";
$stmt = mysqli_prepare($conn, $holdSql);
mysqli_stmt_bind_param($stmt, "iis", $courtSlotId, $userId, $expiresAt);

if (mysqli_stmt_execute($stmt)) {
    $holdId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    header('Location: payment.php?hold_id=' . urlencode((string)$holdId));
    exit;
} else {
    mysqli_stmt_close($stmt);
    http_response_code(500);
    exit('Hold slot failed');
}