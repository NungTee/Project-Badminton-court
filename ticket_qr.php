<?php
// ticket_qr.php
date_default_timezone_set('Asia/Bangkok');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/crud_bootstrap.php';

$bookingId = valid_id($_GET['booking_id'] ?? null);
$userId = (int)($_SESSION['user_id'] ?? 0);
$userRole = $_SESSION['role'] ?? 'PLAYER';

if (!$bookingId || !$userId) {
    header('Location: my_bookings.php');
    exit;
}

// 1. ดึงข้อมูลรายการจอง
$sql = "
    SELECT b.id as booking_id, b.total_price, b.status, b.entry_code, b.is_checked_in, b.user_id as owner_id,
           u.full_name, u.email, u.phone_number,
           cs.slot_date, cs.start_time, cs.end_time, c.court_name, v.name as venue_name
    FROM bookings b
    JOIN badminton_users u ON b.user_id = u.id
    JOIN court_slots cs ON b.court_slot_id = cs.id
    JOIN courts c ON cs.court_id = c.id
    JOIN venues v ON c.venue_id = v.id
    WHERE b.id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    die("Database Query Error: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, "i", $bookingId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$ticket = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

// ตรวจสอบสิทธิ์การเข้าถึง (ต้องเป็นเจ้าของตั๋ว หรือเป็น OWNER เท่านั้น)
if (!$ticket || ($ticket['owner_id'] !== $userId && $userRole !== 'OWNER')) {
    page_start("ไม่พบข้อมูลบัตร");
    echo '<p class="err" style="color:#c62828; background:#ffebee; padding:10px; border-radius:4px;">ไม่พบข้อมูลรายการจองนี้ หรือคุณไม่มีสิทธิ์เข้าถึง</p>';
    echo '<p><a href="my_bookings.php">← กลับสู่รายการจองของฉัน</a></p>';
    page_end();
    exit;
}

// 2. สร้างรหัส Entry Code หากยังไม่มีในฐานข้อมูล
if (empty($ticket['entry_code'])) {
    $code = 'BOOK-' . strtoupper(substr(md5($ticket['booking_id'] . time()), 0, 8));
    $upStmt = mysqli_prepare($conn, "UPDATE bookings SET entry_code = ? WHERE id = ?");
    if ($upStmt) {
        mysqli_stmt_bind_param($upStmt, "si", $code, $ticket['booking_id']);
        mysqli_stmt_execute($upStmt);
        mysqli_stmt_close($upStmt);
        $ticket['entry_code'] = $code;
    }
}

// 3. สร้าง URL สำหรับแสดงผล QR Code
$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=" . urlencode($ticket['entry_code']);

page_start("บัตรยืนยันเข้าสนาม (E-Ticket)");
?>

<div class="card" style="max-width:450px; margin:0 auto; background:#fff; padding:25px; border-radius:12px; border:1px solid #ddd; text-align:center; box-shadow:0 4px 12px rgba(0,0,0,0.1);">
    <h2 style="margin-top:0; color:#135c9a;">🏸 บัตรสแกนเข้าสนาม</h2>
    <p style="color:#666; font-size:0.9rem; margin-bottom:20px;">แสดง QR Code นี้แก่เจ้าหน้าที่หน้าเคาน์เตอร์</p>

    <div style="background:#f9f9f9; padding:15px; border-radius:8px; display:inline-block; border:1px solid #eee;">
        <img src="<?= $qrUrl ?>" alt="QR Code Entry" style="width:200px; height:200px; display:block; margin:0 auto;">
        <span style="display:block; margin-top:8px; font-family:monospace; font-size:1.1rem; font-weight:bold; color:#333;"><?= h($ticket['entry_code']) ?></span>
    </div>

    <div style="text-align:left; margin-top:20px; border-top:1px dashed #ccc; padding-top:15px; font-size:0.95rem; line-height:1.6;">
        <p style="margin:5px 0;">👤 <strong>ชื่อลูกค้า:</strong> <?= h($ticket['full_name']) ?></p>
        <p style="margin:5px 0;">📞 <strong>เบอร์โทรศัพท์:</strong> <?= h($ticket['phone_number'] ?: '-') ?></p>
        <p style="margin:5px 0;">🏟️ <strong>สนาม:</strong> <?= h($ticket['venue_name']) ?> (<?= h($ticket['court_name']) ?>)</p>
        <p style="margin:5px 0;">📅 <strong>วันที่:</strong> <?= h($ticket['slot_date']) ?></p>
        <p style="margin:5px 0;">⏰ <strong>เวลา:</strong> <?= h(substr($ticket['start_time'], 0, 5)) ?> - <?= h(substr($ticket['end_time'], 0, 5)) ?> น.</p>
        <p style="margin:5px 0;">STATUS: 
            <?php if (!empty($ticket['is_checked_in'])): ?>
                <span style="color:green; font-weight:bold;">✅ สแกนเข้าสนามเรียบร้อยแล้ว</span>
            <?php else: ?>
                <span style="color:#ef6c00; font-weight:bold;">⏳ รอสแกนเข้าสนาม</span>
            <?php endif; ?>
        </p>
    </div>

    <div style="margin-top:20px;">
        <a href="my_bookings.php" style="color:#666; text-decoration:none;">← กลับไปยังรายการจองของฉัน</a>
    </div>
</div>

<?php page_end(); ?>