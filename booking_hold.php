<?php
// my_bookings.php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/crud_bootstrap.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$msg = '';
$err = '';

// 1. จัดการการยกเลิกการจอง
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_booking') {
    verify_csrf();
    $bookingId = valid_id($_POST['booking_id'] ?? null);

    if ($bookingId && $userId) {
        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare($conn, "
                SELECT b.id, b.total_price, cs.id as slot_id, cs.slot_date, cs.start_time 
                FROM bookings b
                JOIN court_slots cs ON b.court_slot_id = cs.id
                WHERE b.id = ? AND b.user_id = ? AND b.status = 'CONFIRMED'
            ");
            mysqli_stmt_bind_param($stmt, "ii", $bookingId, $userId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $b = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if ($b) {
                // คำนวณเวลาก่อนเล่น (ถ้ายกเลิกก่อน 24 ชม. คืนเงิน 100%)
                $playTime = strtotime($b['slot_date'] . ' ' . $b['start_time']);
                $hoursDiff = ($playTime - time()) / 3600;
                $refund = ($hoursDiff >= 24) ? $b['total_price'] : 0.00;

                // เปลี่ยนสถานะการจองเป็น CANCELLED
                $stmt = mysqli_prepare($conn, "UPDATE bookings SET status = 'CANCELLED', refund_amount = ?, cancelled_at = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "di", $refund, $bookingId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                // คืนสล็อตเวลาเป็นว่าง (is_available = 1)
                $stmt = mysqli_prepare($conn, "UPDATE court_slots SET is_available = 1 WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $b['slot_id']);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                mysqli_commit($conn);
                $msg = 'ยกเลิกการจองเรียบร้อยแล้ว' . ($refund > 0 ? " (ยอดคืนเงิน: ฿" . number_format($refund, 2) . ")" : " (ไม่เข้าเงื่อนไขคืนเงิน เนื่องจากยกเลิกก่อนแข่งน้อยกว่า 24 ชม.)");
            } else {
                $err = 'ไม่พบรายการจองนี้';
            }
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $err = $e->getMessage();
        }
    }
}

// 2. จัดการการยกเลิกการปล่อยคิวต่อ (Cancel Resale)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_resale') {
    verify_csrf();
    $bookingId = valid_id($_POST['booking_id'] ?? null);

    if ($bookingId && $userId) {
        mysqli_begin_transaction($conn);
        try {
            // ปรับสถานะในตลาดเป็น CANCELLED
            $stmt = mysqli_prepare($conn, "UPDATE resale_slots SET status = 'CANCELLED' WHERE booking_id = ? AND seller_id = ? AND status = 'OPEN'");
            mysqli_stmt_bind_param($stmt, "ii", $bookingId, $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // ดึงสถานะการจองกลับเป็น CONFIRMED
            $stmt = mysqli_prepare($conn, "UPDATE bookings SET status = 'CONFIRMED' WHERE id = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $bookingId, $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);
            $msg = 'ดึงคอร์ทกลับจากการปล่อยขายต่อเรียบร้อยแล้ว!';
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $err = $e->getMessage();
        }
    }
}

// ดึงรายการจองของผู้ใช้นี้ทั้งหมด
$sql = "
    SELECT b.id as booking_id, b.total_price, b.status, b.refund_amount, cs.slot_date, cs.start_time, cs.end_time, c.court_name, v.name as venue_name
    FROM bookings b
    JOIN court_slots cs ON b.court_slot_id = cs.id
    JOIN courts c ON cs.court_id = c.id
    JOIN venues v ON c.venue_id = v.id
    WHERE b.user_id = ?
    ORDER BY cs.slot_date DESC, cs.start_time DESC
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$bookings_res = mysqli_stmt_get_result($stmt);

page_start("รายการจองของฉัน");

if (isset($_GET['success']) && $_GET['success'] === 'booked') echo '<p class="ok" style="background:#e8f5e9; color:#2e7d32; padding:10px; border-radius:4px; font-weight:bold;">✅ จองสนามเรียบร้อยแล้ว!</p>';
if (isset($_GET['success']) && $_GET['success'] === 'bought') echo '<p class="ok" style="background:#e8f5e9; color:#2e7d32; padding:10px; border-radius:4px; font-weight:bold;">✅ รับซื้อคิวต่อเรียบร้อยแล้ว!</p>';
if ($msg) echo '<p class="ok" style="background:#e8f5e9; color:#2e7d32; padding:10px; border-radius:4px; font-weight:bold;">' . h($msg) . '</p>';
if ($err) echo '<p class="err" style="background:#ffebee; color:#c62828; padding:10px; border-radius:4px; font-weight:bold;">' . h($err) . '</p>';
?>

<div class="booking-list" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap:20px; margin-top:20px;">
    <?php while ($b = mysqli_fetch_assoc($bookings_res)): ?>
        <div class="card" style="background:#fff; border:1px solid #ddd; padding:15px; border-radius:8px;">
            <h3 style="margin-top:0; color:#135c9a;"><?= h($b['venue_name']) ?> (<?= h($b['court_name']) ?>)</h3>
            <p style="margin:5px 0;">📅 วันที่: <strong><?= h($b['slot_date']) ?></strong></p>
            <p style="margin:5px 0;">⏰ เวลา: <strong><?= h(substr($b['start_time'], 0, 5)) ?> - <?= h(substr($b['end_time'], 0, 5)) ?> น.</strong></p>
            <p style="margin:5px 0;">ยอดชำระ: ฿<?= h(number_format($b['total_price'], 2)) ?></p>
            <p style="margin:5px 0;">สถานะ: 
                <?php if ($b['status'] === 'CONFIRMED'): ?>
                    <span style="color:green; font-weight:bold;">ยืนยันแล้ว</span>
                <?php elseif ($b['status'] === 'FOR_RESALE'): ?>
                    <span style="color:#ef6c00; font-weight:bold;">อยู่ระหว่างปล่อยคิวต่อ</span>
                <?php elseif ($b['status'] === 'CANCELLED'): ?>
                    <span style="color:red; font-weight:bold;">ยกเลิกแล้ว</span> <?= $b['refund_amount'] > 0 ? "(คืนเงิน ฿".number_format($b['refund_amount']).")" : "" ?>
                <?php endif; ?>
            </p>

            <div style="margin-top:15px; display:flex; gap:10px; flex-wrap:wrap;">
                <?php if ($b['status'] === 'CONFIRMED'): ?>
                    <!-- ปุ่มปล่อยคิวต่อ -->
                    <form method="post" action="resale_market.php" style="margin:0;">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="list_resale">
                        <input type="hidden" name="booking_id" value="<?= h($b['booking_id']) ?>">
                        <button type="submit" style="background:#ef6c00; color:white; border:none; padding:8px 12px; border-radius:4px; cursor:pointer;">🔁 ปล่อยคิวต่อ</button>
                    </form>

                    <!-- ปุ่มยกเลิกการจอง -->
                    <form method="post" onsubmit="return confirm('ยืนยันยกเลิกการจอง? (ระบบจะคำนวณการคืนเงินตามนโยบาย)');" style="margin:0;">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="cancel_booking">
                        <input type="hidden" name="booking_id" value="<?= h($b['booking_id']) ?>">
                        <button type="submit" style="background:#c62828; color:white; border:none; padding:8px 12px; border-radius:4px; cursor:pointer;">❌ ยกเลิกการจอง</button>
                    </form>
                <?php elseif ($b['status'] === 'FOR_RESALE'): ?>
                    <!-- ปุ่มดึงคอร์ทกลับจากการปล่อยขายต่อ -->
                    <form method="post" style="margin:0;">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="cancel_resale">
                        <input type="hidden" name="booking_id" value="<?= h($b['booking_id']) ?>">
                        <button type="submit" style="background:#1565c0; color:white; border:none; padding:8px 12px; border-radius:4px; cursor:pointer;">↩️ ยกเลิกปล่อยต่อ (ดึงกลับ)</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endwhile; ?>
</div>

<?php page_end(); ?>