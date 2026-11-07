<?php
// my_bookings.php
date_default_timezone_set('Asia/Bangkok');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/crud_bootstrap.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
if (!$userId) {
    header('Location: login.php');
    exit;
}

$msg = '';
$err = '';

// 1. จัดการการยกเลิกการจองสนาม (Cancel Booking)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_booking') {
    verify_csrf();
    $bookingId = valid_id($_POST['booking_id'] ?? null);

    if ($bookingId) {
        mysqli_begin_transaction($conn);
        try {
            // ดึงข้อมูลรายการจอง (เช็กสิทธิ์ของผู้ใช้)
            $stmt = mysqli_prepare($conn, "SELECT id, court_slot_id, status FROM bookings WHERE id = ? AND user_id = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt, "ii", $bookingId, $userId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $booking = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if (!$booking) {
                throw new Exception("ไม่พบรายการจองนี้ หรือคุณไม่มีสิทธิ์ยกเลิก");
            }

            if ($booking['status'] === 'CANCELLED') {
                throw new Exception("รายการจองนี้ถูกยกเลิกไปแล้ว");
            }

            // เปลี่ยนสถานะรายการจองเป็น CANCELLED
            $upBooking = mysqli_prepare($conn, "UPDATE bookings SET status = 'CANCELLED' WHERE id = ?");
            mysqli_stmt_bind_param($upBooking, "i", $bookingId);
            mysqli_stmt_execute($upBooking);
            mysqli_stmt_close($upBooking);

            // คืนสถานะสล็อตเวลาให้กลับมาว่าง (is_available = 1)
            $upSlot = mysqli_prepare($conn, "UPDATE court_slots SET is_available = 1 WHERE id = ?");
            mysqli_stmt_bind_param($upSlot, "i", $booking['court_slot_id']);
            mysqli_stmt_execute($upSlot);
            mysqli_stmt_close($upSlot);

            // ลบรายการสล็อตนี้ออกจากตลาดคอร์ทหลุด (ถ้ามีตาราง resale_slots)
            @mysqli_query($conn, "DELETE FROM resale_slots WHERE booking_id = " . (int)$bookingId);

            mysqli_commit($conn);
            $msg = "ยกเลิกการจองสนามรายการ #$bookingId เรียบร้อยแล้ว (คืนสล็อตเวลาเข้าระบบแล้ว)";
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $err = $e->getMessage();
        }
    }
}

// 2. ดึงรายการจองทั้งหมดของผู้ใช้ (เขียนแบบปลอดภัยรองรับโครงสร้างตาราง DB)
$sql = "
    SELECT b.id as booking_id, b.total_price, b.status, b.created_at,
           cs.slot_date, cs.start_time, cs.end_time, c.court_name, c.court_type, v.name as venue_name
    FROM bookings b
    JOIN court_slots cs ON b.court_slot_id = cs.id
    JOIN courts c ON cs.court_id = c.id
    JOIN venues v ON c.venue_id = v.id
    WHERE b.user_id = ?
    ORDER BY cs.slot_date DESC, cs.start_time DESC
";

$stmt = mysqli_prepare($conn, $sql);
$bookings = [];

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $bookings[] = $row;
    }
    mysqli_stmt_close($stmt);
} else {
    $err = "เกิดข้อผิดพลาดในคำสั่งดึงข้อมูล: " . mysqli_error($conn);
}

page_start("รายการจองของฉัน");
?>

<?php if ($msg): ?><div class="ok">✅ <?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="err">⚠️ <?= h($err) ?></div><?php endif; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
    <h2 style="margin:0; color:var(--primary-dark);">📋 ประวัติและรายการจองสนามทั้งหมด</h2>
    <a href="index.php" style="background:#0f172a; color:white; text-decoration:none; padding:8px 18px; border-radius:30px; font-weight:600; font-size:0.9rem;">+ จองสนามเพิ่ม</a>
</div>

<?php if (!empty($bookings)): ?>
    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:20px;">
        <?php foreach ($bookings as $b): 
            $isCancelled = ($b['status'] === 'CANCELLED');
        ?>
            <div style="background:#fff; border:1px solid var(--border-color); border-radius:16px; padding:20px; box-shadow:0 4px 15px rgba(0,0,0,0.03); display:flex; flex-direction:column; justify-content:space-between;">
                <div>
                    <!-- Header การ์ด -->
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; border-bottom:1px solid #f1f5f9; padding-bottom:10px;">
                        <div>
                            <span style="font-size:0.8rem; color:var(--text-muted); font-weight:bold;">BOOKING #<?= h($b['booking_id']) ?></span>
                            <h3 style="margin:2px 0 0 0; color:var(--primary-dark); font-size:1.15rem;"><?= h($b['venue_name']) ?></h3>
                        </div>
                        <div>
                            <?php if ($isCancelled): ?>
                                <span class="badge-status badge-booked">❌ ยกเลิกแล้ว</span>
                            <?php else: ?>
                                <span class="badge-status badge-available">🟢 จองสำเร็จ</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- รายละเอียดสนามและเวลา -->
                    <div style="font-size:0.95rem; color:var(--text-main); line-height:1.7; margin-bottom:15px;">
                        <p style="margin:2px 0;">🏟️ <strong>คอร์ท:</strong> <?= h($b['court_name']) ?> <span class="badge-type"><?= ($b['court_type'] ?? '') === 'RUBBER' ? 'ยาง PBSI' : 'ไม้ปาร์เก้' ?></span></p>
                        <p style="margin:2px 0;">📅 <strong>วันที่:</strong> <?= h($b['slot_date']) ?></p>
                        <p style="margin:2px 0;">⏰ <strong>เวลา:</strong> <?= h(substr($b['start_time'], 0, 5)) ?> - <?= h(substr($b['end_time'], 0, 5)) ?> น.</p>
                        <p style="margin:2px 0; font-size:1.1rem; color:var(--sport-available-text); font-weight:bold; margin-top:6px;">💰 ยอดชำระ: ฿<?= number_format($b['total_price'], 2) ?></p>
                    </div>
                </div>

                <!-- ปุ่มการทำงานต่างๆ -->
                <?php if (!$isCancelled): ?>
                    <div style="display:flex; flex-direction:column; gap:8px; margin-top:10px; border-top:1px dashed #e2e8f0; padding-top:12px;">
                        <div style="display:flex; gap:8px;">
                            <!-- ปุ่ม QR Code -->
                            <a href="ticket_qr.php?booking_id=<?= h($b['booking_id']) ?>" style="flex:1; text-align:center; background:#0f172a; color:white; text-decoration:none; padding:8px; border-radius:10px; font-weight:bold; font-size:0.88rem;">
                                🎫 QR เข้าสนาม
                            </a>

                            <!-- ปุ่มหารค่าคอร์ท -->
                            <a href="split_bill.php?booking_id=<?= h($b['booking_id']) ?>" style="flex:1; text-align:center; background:#1e40af; color:white; text-decoration:none; padding:8px; border-radius:10px; font-weight:bold; font-size:0.88rem;">
                                🍕 หารค่าคอร์ท
                            </a>
                        </div>

                        <div style="display:flex; gap:8px;">
                            <!-- ปุ่มลงขายตลาดคอร์ทหลุด -->
                            <a href="resale_market.php?action=sell&booking_id=<?= h($b['booking_id']) ?>" style="flex:1; text-align:center; background:#c2410c; color:white; text-decoration:none; padding:8px; border-radius:10px; font-weight:bold; font-size:0.85rem;">
                                🔄 ส่งต่อคอร์ท
                            </a>

                            <!-- ปุ่มยกเลิกการจอง -->
                            <form method="post" onsubmit="return confirm('ยืนยันยกเลิกการจองสนามนี้? (สล็อตเวลาจะถูกคืนเข้าระบบเพื่อให้ผู้อื่นจอง)');" style="margin:0; flex:1;">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="cancel_booking">
                                <input type="hidden" name="booking_id" value="<?= h($b['booking_id']) ?>">
                                <button type="submit" style="width:100%; background:#dc2626; color:white; border:none; padding:8px; border-radius:10px; font-weight:bold; font-size:0.85rem; cursor:pointer;">
                                    ❌ ยกเลิกจอง
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div style="text-align:center; padding:50px 20px; background:#fff; border-radius:16px; border:1px dashed #cbd5e1; margin-top:20px;">
        <p style="font-size:1.1rem; color:var(--text-muted); margin-bottom:15px;">คุณยังไม่มีประวัติการจองสนามแบดมินตัน</p>
        <a href="index.php" style="background:#0f172a; color:white; text-decoration:none; padding:10px 24px; border-radius:30px; font-weight:bold;">🏸 เริ่มจองสนามเลย</a>
    </div>
<?php endif; ?>

<?php page_end(); ?>