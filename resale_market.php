<?php
// resale_market.php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/crud_bootstrap.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$msg = '';
$err = '';

// 1. จัดการการนำคอร์ทลงตลาด (ปล่อยคิวต่อ)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'list_resale') {
    verify_csrf();
    $bookingId = valid_id($_POST['booking_id'] ?? null);

    if ($bookingId && $userId) {
        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare($conn, "SELECT total_price FROM bookings WHERE id = ? AND user_id = ? AND status = 'CONFIRMED'");
            mysqli_stmt_bind_param($stmt, "ii", $bookingId, $userId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $b = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if ($b) {
                $stmt = mysqli_prepare($conn, "UPDATE bookings SET status = 'FOR_RESALE' WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $bookingId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $stmt = mysqli_prepare($conn, "INSERT INTO resale_slots (booking_id, seller_id, resale_price, status) VALUES (?, ?, ?, 'OPEN')");
                mysqli_stmt_bind_param($stmt, "iid", $bookingId, $userId, $b['total_price']);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                mysqli_commit($conn);
                header('Location: resale_market.php?success=listed');
                exit;
            } else {
                $err = 'ไม่พบรายการจองนี้ หรือรายการนี้ถูกนำลงตลาดไปแล้ว';
            }
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $err = 'เกิดข้อผิดพลาดในการนำลงตลาด: ' . $e->getMessage();
        }
    }
}

// 2. จัดการการกดซื้อคิวต่อ (Buy Resale)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'buy_resale') {
    verify_csrf();
    $resaleId = valid_id($_POST['resale_id'] ?? null);

    if ($resaleId && $userId) {
        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare($conn, "SELECT id, booking_id, seller_id, resale_price FROM resale_slots WHERE id = ? AND status = 'OPEN'");
            mysqli_stmt_bind_param($stmt, "i", $resaleId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $r = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if (!$r) {
                throw new Exception("รายการนี้ถูกซื้อไปแล้วหรือถูกยกเลิก");
            }

            if ($r['seller_id'] == $userId) {
                throw new Exception("คุณไม่สามารถซื้อคิวของตนเองได้");
            }

            // อัปเดตสถานะในตลาดเป็น SOLD
            $stmt = mysqli_prepare($conn, "UPDATE resale_slots SET status = 'SOLD' WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $resaleId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // โอนสิทธิ์การจองในตาราง bookings ให้กับผู้ซื้อคนใหม่
            $stmt = mysqli_prepare($conn, "UPDATE bookings SET user_id = ?, status = 'CONFIRMED' WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $userId, $r['booking_id']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // บันทึกการชำระเงินจำลอง (ใช้วิธี ignore หรือปรับแต่งเพื่อรองรับการบันทึกเพิ่ม)
            $paySql = "INSERT INTO payments (booking_id, amount, payment_method, status) VALUES (?, ?, 'RESALE_SIMULATION', 'SUCCESS')";
            if ($stmt = mysqli_prepare($conn, $paySql)) {
                mysqli_stmt_bind_param($stmt, "id", $r['booking_id'], $r['resale_price']);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            mysqli_commit($conn);
            header('Location: my_bookings.php?success=bought');
            exit;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $err = $e->getMessage();
        }
    }
}

// ดึงรายการคอร์ทหลุดทั้งหมดที่เปิดขายอยู่
$sql = "
    SELECT r.id as resale_id, r.seller_id, r.resale_price, cs.slot_date, cs.start_time, cs.end_time, c.court_name, v.name as venue_name, u.full_name as seller_name
    FROM resale_slots r
    JOIN bookings b ON r.booking_id = b.id
    JOIN court_slots cs ON b.court_slot_id = cs.id
    JOIN courts c ON cs.court_id = c.id
    JOIN venues v ON c.venue_id = v.id
    JOIN badminton_users u ON r.seller_id = u.id
    WHERE r.status = 'OPEN'
    ORDER BY cs.slot_date ASC, cs.start_time ASC
";
$resales = mysqli_query($conn, $sql);

page_start("ตลาดคอร์ทหลุด / ปล่อยคิวต่อ");

if (isset($_GET['success']) && $_GET['success'] === 'listed') {
    echo '<p class="ok" style="background:#e8f5e9; color:#2e7d32; padding:10px; border-radius:4px; font-weight:bold; margin-bottom:15px;">✅ นำคอร์ทลงตลาดปล่อยคิวต่อเรียบร้อยแล้ว!</p>';
}
if ($err) {
    echo '<p class="err" style="background:#ffebee; color:#c62828; padding:10px; border-radius:4px; font-weight:bold; margin-bottom:15px;">⚠️ ' . h($err) . '</p>';
}
?>

<div class="resale-list" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:20px; margin-top:15px;">
    <?php if ($resales && mysqli_num_rows($resales) > 0): ?>
        <?php while ($r = mysqli_fetch_assoc($resales)): ?>
            <div class="card resale-card" style="background:#fff; border:1px solid #ddd; padding:15px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.05); display:flex; flex-direction:column; justify-content:space-between;">
                <div>
                    <h3 style="margin-top:0; color:#135c9a;"><?= h($r['venue_name']) ?> (<?= h($r['court_name']) ?>)</h3>
                    <p style="margin:5px 0;">📅 วันที่: <strong><?= h($r['slot_date']) ?></strong></p>
                    <p style="margin:5px 0;">⏰ เวลา: <strong><?= h(substr($r['start_time'], 0, 5)) ?> - <?= h(substr($r['end_time'], 0, 5)) ?> น.</strong></p>
                    <p style="margin:5px 0; color:#555;">👤 ผู้ปล่อยต่อ: <?= h($r['seller_name']) ?></p>
                    <p class="price-tag" style="font-size:1.2rem; color:#2e7d32; font-weight:bold; margin:10px 0;">ราคาปล่อยต่อ: ฿<?= h(number_format($r['resale_price'])) ?></p>
                </div>

                <div style="margin-top:15px;">
                    <?php if ($r['seller_id'] == $userId): ?>
                        <button disabled style="width:100%; padding:10px; background:#ccc; color:#666; border:none; border-radius:4px; font-weight:bold; cursor:not-allowed;">คิวของคุณเอง</button>
                    <?php else: ?>
                        <form method="post" onsubmit="return confirm('ยืนยันรับคิวต่อสนามนี้?');">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="buy_resale">
                            <input type="hidden" name="resale_id" value="<?= h($r['resale_id']) ?>">
                            <button type="submit" style="width:100%; padding:10px; background:#2e7d32; color:white; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">
                                🛒 ซื้อคิวต่อ
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div style="grid-column: 1 / -1; text-align:center; padding:30px; background:#f9f9f9; border-radius:8px; border:1px solid #eee;">
            <p style="color:#777; font-size:1rem; margin:0;">ยังไม่มีรายการคอร์ทหลุดในขณะนี้</p>
        </div>
    <?php endif; ?>
</div>

<?php page_end(); ?>