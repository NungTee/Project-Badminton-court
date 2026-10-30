<?php
// payment.php
require_once __DIR__ . '/crud_bootstrap.php';

$holdId = valid_id($_GET['hold_id'] ?? null);
$userId = (int)$_SESSION['user_id'];
$error = '';

if (!$holdId) {
    header('Location: index.php');
    exit;
}

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
    echo '<p class="err">รายการจองของคุณหมดเวลาแล้ว กรุณาเลือกสล็อตเวลาใหม่อีกครั้ง</p>';
    echo '<p><a href="index.php">กลับหน้าจองสนาม</a></p>';
    page_end();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    mysqli_begin_transaction($conn);
    try {
        $bookSql = "INSERT INTO bookings (user_id, court_slot_id, total_price, status, booking_source) VALUES (?, ?, ?, 'CONFIRMED', 'ONLINE')";
        $stmt = mysqli_prepare($conn, $bookSql);
        mysqli_stmt_bind_param($stmt, "iid", $userId, $details['slot_id'], $details['price']);
        if (!mysqli_stmt_execute($stmt)) throw new Exception("Booking failed");
        $bookingId = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $paySql = "INSERT INTO payments (booking_id, amount, payment_method, status) VALUES (?, ?, 'PROMPTPAY', 'SUCCESS')";
        $stmt = mysqli_prepare($conn, $paySql);
        mysqli_stmt_bind_param($stmt, "id", $bookingId, $details['price']);
        if (!mysqli_stmt_execute($stmt)) throw new Exception("Payment failed");
        mysqli_stmt_close($stmt);

        $updateSlot = "UPDATE court_slots SET is_available = 0 WHERE id = ?";
        $stmt = mysqli_prepare($conn, $updateSlot);
        mysqli_stmt_bind_param($stmt, "i", $details['slot_id']);
        if (!mysqli_stmt_execute($stmt)) throw new Exception("Update slot failed");
        mysqli_stmt_close($stmt);

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
        $error = 'การชำระเงินล้มเหลว กรุณาลองใหม่อีกครั้ง';
    }
}

page_start("ชำระเงินเพื่อยืนยันการจอง");
?>

<?php if ($error): ?><p class="err"><?= h($error) ?></p><?php endif; ?>

<div class="card payment-card">
    <h2><?= h($details['venue_name']) ?> - <?= h($details['court_name']) ?></h2>
    <p>📅 วันที่: <strong><?= h($details['slot_date']) ?></strong></p>
    <p>⏰ เวลา: <strong><?= h(substr($details['start_time'], 0, 5)) ?> - <?= h(substr($details['end_time'], 0, 5)) ?> น.</strong></p>
    <p class="price-tag">ยอดชำระ: <strong>฿<?= h(number_format($details['price'], 2)) ?></strong></p>

    <div class="qr-box">
        <h3>สแกนจ่ายผ่าน PromptPay QR Code</h3>
        <p class="timer">กรุณาชำระเงินภายในระบบล็อกเวลาไว้ให้คุณ 3 นาที</p>
    </div>

    <form method="post">
        <?php csrf_field(); ?>
        <button type="submit" class="btn-confirm">ยืนยันการชำระเงินเรียบร้อย</button>
        <a href="index.php" class="btn-cancel">ยกเลิกรายการ</a>
    </form>
</div>

<?php page_end(); ?>