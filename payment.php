<?php
// payment.php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/crud_bootstrap.php';

$holdId = valid_id($_GET['hold_id'] ?? null);
$userId = (int)($_SESSION['user_id'] ?? 0);
$error = '';

if (!$holdId) {
    header('Location: index.php');
    exit;
}

// ดึงรายละเอียดการ Hold
$sql = "
    SELECT sh.id as hold_id, sh.expires_at, cs.id as slot_id, cs.slot_date, cs.start_time, cs.end_time, cs.price, c.court_name, v.name as venue_name
    FROM slot_holds sh
    JOIN court_slots cs ON sh.court_slot_id = cs.id
    JOIN courts c ON cs.court_id = c.id
    JOIN venues v ON c.venue_id = v.id
    WHERE sh.id = ? AND sh.user_id = ? AND sh.expires_at > NOW()
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $holdId, $userId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$details = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$details) {
    page_start("การจองหมดเวลา");
    echo '<p class="err" style="color:#c62828; background:#ffebee; padding:10px; border-radius:4px; margin-bottom:15px;">รายการจองของคุณหมดเวลาแล้ว กรุณาเลือกสล็อตเวลาใหม่อีกครั้ง</p>';
    echo '<p><a href="index.php" style="color:#135c9a; text-decoration:none; font-weight:bold;">← กลับหน้าจองสนาม</a></p>';
    page_end();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    mysqli_begin_transaction($conn);
    try {
        // 1. บันทึกข้อมูลการจองลงตาราง bookings
        $bookSql = "INSERT INTO bookings (user_id, court_slot_id, total_price, status, booking_source) VALUES (?, ?, ?, 'CONFIRMED', 'ONLINE')";
        $stmt = mysqli_prepare($conn, $bookSql);
        mysqli_stmt_bind_param($stmt, "iid", $userId, $details['slot_id'], $details['price']);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Booking failed: " . mysqli_stmt_error($stmt));
        }
        $bookingId = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        // 2. บันทึกประวัติการชำระเงินจำลองลงตาราง payments (ถ้ามีตาราง)
        $paySql = "INSERT INTO payments (booking_id, amount, payment_method, status) VALUES (?, ?, 'SIMULATION', 'SUCCESS')";
        if ($stmt = mysqli_prepare($conn, $paySql)) {
            mysqli_stmt_bind_param($stmt, "id", $bookingId, $details['price']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        // 3. ปรับสถานะสล็อตเวลาเป็น "จองแล้ว" (is_available = 0)
        $updateSlot = "UPDATE court_slots SET is_available = 0 WHERE id = ?";
        $stmt = mysqli_prepare($conn, $updateSlot);
        mysqli_stmt_bind_param($stmt, "i", $details['slot_id']);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Update slot failed: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        // 4. ลบสล็อตออกจากตาราง slot_holds
        $delHold = "DELETE FROM slot_holds WHERE id = ?";
        $stmt = mysqli_prepare($conn, $delHold);
        mysqli_stmt_bind_param($stmt, "i", $holdId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conn);
        header('Location: my_bookings.php?success=booked');
        exit;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        $error = 'เกิดข้อผิดพลาดในการบันทึกการจอง: ' . $e->getMessage();
    }
}

page_start("ยืนยันการจองสนาม");
?>

<?php if ($error): ?>
    <p class="err" style="color:#c62828; background:#ffebee; padding:10px; border-radius:4px; margin-bottom:15px;"><?= h($error) ?></p>
<?php endif; ?>

<div class="card payment-card" style="background:#fff; padding:20px; border:1px solid #ddd; border-radius:8px; max-width:500px; margin:0 auto;">
    <h2><?= h($details['venue_name']) ?> - <?= h($details['court_name']) ?></h2>
    <p>📅 วันที่: <strong><?= h($details['slot_date']) ?></strong></p>
    <p>⏰ เวลา: <strong><?= h(substr($details['start_time'], 0, 5)) ?> - <?= h(substr($details['end_time'], 0, 5)) ?> น.</strong></p>
    <p class="price-tag" style="font-size:1.2rem; color:#2e7d32; font-weight:bold; margin:15px 0;">ยอดชำระ: ฿<?= h(number_format($details['price'], 2)) ?></p>

    <div class="qr-box" style="background:#f9f9f9; padding:15px; border-radius:6px; text-align:center; margin-bottom:20px;">
        <h3 style="margin-top:0; color:#135c9a;">📌 ระบบจองสนามจำลอง (Demo Only)</h3>
        <p style="color:#555; font-size:0.95rem;">กดปุ่มด้านล่างเพื่อยืนยันการจองสนามได้ทันที โดยไม่ต้องโอนเงินจริง</p>
        <p class="timer" style="color:#ef6c00; font-size:0.85rem; margin-top:5px;">ระบบล็อกเวลาคอร์ทไว้ให้คุณเป็นเวลา 5 นาที</p>
    </div>

    <form method="post">
        <?php csrf_field(); ?>
        <button type="submit" class="btn-confirm" style="width:100%; padding:12px; background:#2e7d32; color:white; border:none; border-radius:6px; font-weight:bold; font-size:1rem; cursor:pointer;">
            ✅ ยืนยันการจองสนามทันที
        </button>
        <p style="text-align:center; margin-top:10px;">
            <a href="index.php" class="btn-cancel" style="color:#666; text-decoration:none;">ยกเลิกรายการ</a>
        </p>
    </form>
</div>

<?php page_end(); ?>