<?php
// resale_market.php
date_default_timezone_set('Asia/Bangkok');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/crud_bootstrap.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$msg = '';
$err = '';

// ตรวจสอบชื่อคอลัมน์ในตาราง resale_slots ว่าใช้ seller_user_id หรือ seller_id
$sellerColumn = 'seller_user_id';
$colCheck = @mysqli_query($conn, "SHOW COLUMNS FROM resale_slots LIKE 'seller_user_id'");
if (!$colCheck || mysqli_num_rows($colCheck) == 0) {
    $sellerColumn = 'seller_id';
}

// 1. จัดการการลงประกาศขายคอร์ทหลุด
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'post_resale') {
    verify_csrf();
    $bookingId = valid_id($_POST['booking_id'] ?? null);
    $resalePrice = filter_var($_POST['resale_price'] ?? 0, FILTER_VALIDATE_FLOAT);

    if ($bookingId && $resalePrice !== false && $resalePrice > 0) {
        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare($conn, "SELECT id, total_price FROM bookings WHERE id = ? AND user_id = ? AND status = 'CONFIRMED' FOR UPDATE");
            mysqli_stmt_bind_param($stmt, "ii", $bookingId, $userId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $b = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if (!$b) {
                throw new Exception("ไม่พบรายการจองนี้ หรือไม่สามารถนำมาลงขายได้");
            }

            $chkStmt = mysqli_prepare($conn, "SELECT id FROM resale_slots WHERE booking_id = ? AND status = 'OPEN'");
            mysqli_stmt_bind_param($chkStmt, "i", $bookingId);
            mysqli_stmt_execute($chkStmt);
            $chkRes = mysqli_stmt_get_result($chkStmt);
            if (mysqli_num_rows($chkRes) > 0) {
                throw new Exception("รายการนี้ถูกลงขายอยู่ในตลาดแล้ว");
            }
            mysqli_stmt_close($chkStmt);

            // บันทึกแบบระบุชื่อคอลัมน์ตาม DB ที่มีอยู่จริง
            $insQuery = "INSERT INTO resale_slots (booking_id, {$sellerColumn}, resale_price, status) VALUES (?, ?, ?, 'OPEN')";
            $insStmt = mysqli_prepare($conn, $insQuery);
            if (!$insStmt) {
                throw new Exception("เกิดข้อผิดพลาดในการสร้างคำสั่ง SQL: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($insStmt, "iid", $bookingId, $userId, $resalePrice);
            mysqli_stmt_execute($insStmt);
            mysqli_stmt_close($insStmt);

            mysqli_commit($conn);
            $msg = "ลงประกาศส่งต่อคอร์ทหลุดเรียบร้อยแล้ว!";
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $err = $e->getMessage();
        }
    } else {
        $err = "กรุณาระบุราคาขายส่งต่อให้ถูกต้อง";
    }
}

// 2. จัดการการซื้อคอร์ทต่อจากตลาด
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'buy_resale') {
    verify_csrf();
    $resaleId = valid_id($_POST['resale_id'] ?? null);

    if ($resaleId) {
        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare($conn, "SELECT rs.*, b.id as booking_id FROM resale_slots rs JOIN bookings b ON rs.booking_id = b.id WHERE rs.id = ? AND rs.status = 'OPEN' FOR UPDATE");
            mysqli_stmt_bind_param($stmt, "i", $resaleId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $item = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if (!$item) {
                throw new Exception("รายการขายนี้ถูกซื้อไปแล้วหรือถูกยกเลิก");
            }

            $sellerId = $item[$sellerColumn] ?? 0;
            if ($sellerId == $userId) {
                throw new Exception("คุณไม่สามารถซื้อคอร์ทที่ตัวเองลงขายได้");
            }

            $upBooking = mysqli_prepare($conn, "UPDATE bookings SET user_id = ? WHERE id = ?");
            mysqli_stmt_bind_param($upBooking, "ii", $userId, $item['booking_id']);
            mysqli_stmt_execute($upBooking);
            mysqli_stmt_close($upBooking);

            $upResale = mysqli_prepare($conn, "UPDATE resale_slots SET status = 'SOLD' WHERE id = ?");
            mysqli_stmt_bind_param($upResale, "i", $resaleId);
            mysqli_stmt_execute($upResale);
            mysqli_stmt_close($upResale);

            mysqli_commit($conn);
            $msg = "คุณซื้อต่อคอร์ทสำเร็จแล้ว! สามารถดูตั๋วเข้าสนามได้ในหน้า 'รายการจองของฉัน'";
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $err = $e->getMessage();
        }
    }
}

// 3. ดึงรายการคอร์ทหลุดที่กำลังเปิดขายอยู่
$marketSql = "
    SELECT rs.id as resale_id, rs.resale_price, rs.created_at, rs.{$sellerColumn} as seller_user_id,
           b.id as booking_id, b.total_price as original_price,
           cs.slot_date, cs.start_time, cs.end_time,
           c.court_name, c.court_type, v.name as venue_name,
           u.full_name as seller_name
    FROM resale_slots rs
    JOIN bookings b ON rs.booking_id = b.id
    JOIN court_slots cs ON b.court_slot_id = cs.id
    JOIN courts c ON cs.court_id = c.id
    JOIN venues v ON c.venue_id = v.id
    JOIN badminton_users u ON rs.{$sellerColumn} = u.id
    WHERE rs.status = 'OPEN' AND cs.slot_date >= CURDATE()
    ORDER BY cs.slot_date ASC, cs.start_time ASC
";
$marketRes = mysqli_query($conn, $marketSql);

page_start("ตลาดคอร์ทหลุด (Resale Market)");
?>

<?php if ($msg): ?><div class="ok">✅ <?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="err">⚠️ <?= h($err) ?></div><?php endif; ?>

<!-- กรณีที่ส่งค่า action=sell มาจากหน้า my_bookings.php เพื่อลงขาย -->
<?php if (($_GET['action'] ?? '') === 'sell' && isset($_GET['booking_id'])): 
    $sellBookingId = valid_id($_GET['booking_id']);
    $stmt = mysqli_prepare($conn, "SELECT b.total_price, cs.slot_date, cs.start_time, cs.end_time, c.court_name FROM bookings b JOIN court_slots cs ON b.court_slot_id = cs.id JOIN courts c ON cs.court_id = c.id WHERE b.id = ? AND b.user_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $sellBookingId, $userId);
    mysqli_stmt_execute($stmt);
    $sellItem = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
?>
    <?php if ($sellItem): ?>
        <div style="background:#fff7ed; border:1px solid #fed7aa; padding:20px; border-radius:16px; margin-bottom:25px;">
            <h3 style="margin-top:0; color:#c2410c;">🔄 ลงประกาศส่งต่อคอร์ทหลุด (Booking #<?= h($sellBookingId) ?>)</h3>
            <p style="margin:5px 0;"><strong>สนาม:</strong> <?= h($sellItem['court_name']) ?> | <strong>วันที่:</strong> <?= h($sellItem['slot_date']) ?> (<?= h(substr($sellItem['start_time'],0,5)) ?> - <?= h(substr($sellItem['end_time'],0,5)) ?> น.)</p>
            <p style="margin:5px 0;"><strong>ราคาจองเดิม:</strong> ฿<?= number_format($sellItem['total_price'], 2) ?></p>

            <form method="post" style="margin-top:15px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="post_resale">
                <input type="hidden" name="booking_id" value="<?= h($sellBookingId) ?>">

                <label style="font-weight:bold;">ตั้งราคาขายส่งต่อ (บาท):</label>
                <input type="number" name="resale_price" value="<?= h($sellItem['total_price']) ?>" step="0.01" min="1" required style="padding:8px 12px; border-radius:8px; border:1px solid #cbd5e1; width:120px;">
                
                <button type="submit" style="background:#c2410c; color:white; border:none; padding:9px 20px; border-radius:20px; font-weight:bold; cursor:pointer;">
                    🚀 ยืนยันลงประกาศขาย
                </button>
                <a href="resale_market.php" style="color:#64748b; text-decoration:none;">ยกเลิก</a>
            </form>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div style="margin-bottom:20px;">
    <h2 style="margin:0; color:var(--primary-dark);">🔥 รายการคอร์ทหลุดที่กำลังเปิดส่งต่อ</h2>
    <p style="color:var(--text-muted); font-size:0.95rem; margin-top:4px;">เลือกซื้อคอร์ทหลุดจากเพื่อนสมาชิกที่ติดภารกิจกระทันหันในราคาสุดคุ้ม</p>
</div>

<?php if ($marketRes && mysqli_num_rows($marketRes) > 0): ?>
    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:20px;">
        <?php while ($item = mysqli_fetch_assoc($marketRes)): 
            $isMine = ($item['seller_user_id'] == $userId);
        ?>
            <div style="background:#fff; border:1px solid var(--border-color); border-radius:16px; padding:20px; box-shadow:0 4px 15px rgba(0,0,0,0.03); display:flex; flex-direction:column; justify-content:space-between;">
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; border-bottom:1px solid #f1f5f9; padding-bottom:8px;">
                        <span style="font-size:0.8rem; color:var(--text-muted); font-weight:bold;">ผู้ส่งต่อ: <?= h($item['seller_name']) ?></span>
                        <span class="badge-status badge-holding">🔄 คอร์ทหลุด</span>
                    </div>

                    <h3 style="margin:0 0 8px 0; color:var(--primary-dark); font-size:1.15rem;"><?= h($item['venue_name']) ?></h3>
                    <p style="margin:2px 0; font-size:0.95rem;">🏟️ <strong>คอร์ท:</strong> <?= h($item['court_name']) ?> <span class="badge-type"><?= $item['court_type'] === 'RUBBER' ? 'ยาง PBSI' : 'ไม้ปาร์เก้' ?></span></p>
                    <p style="margin:2px 0; font-size:0.95rem;">📅 <strong>วันที่:</strong> <?= h($item['slot_date']) ?></p>
                    <p style="margin:2px 0; font-size:0.95rem;">⏰ <strong>เวลา:</strong> <?= h(substr($item['start_time'],0,5)) ?> - <?= h(substr($item['end_time'],0,5)) ?> น.</p>
                    
                    <div style="margin-top:12px; background:#f8fafc; padding:10px; border-radius:10px; display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:0.85rem; color:#64748b; text-decoration:line-through;">ปกติ ฿<?= number_format($item['original_price'], 2) ?></span>
                        <span style="font-size:1.25rem; color:#047857; font-weight:bold;">ส่งต่อ ฿<?= number_format($item['resale_price'], 2) ?></span>
                    </div>
                </div>

                <div style="margin-top:15px;">
                    <?php if ($isMine): ?>
                        <button disabled style="width:100%; background:#e2e8f0; color:#64748b; border:none; padding:10px; border-radius:10px; font-weight:bold; cursor:not-allowed;">
                            คอร์ทของคุณเอง
                        </button>
                    <?php else: ?>
                        <form method="post" onsubmit="return confirm('ยืนยันชำระเงินเพื่อรับช่วงต่อคอร์ทนี้?');" style="margin:0;">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="buy_resale">
                            <input type="hidden" name="resale_id" value="<?= h($item['resale_id']) ?>">
                            <button type="submit" style="width:100%; background:#047857; color:white; border:none; padding:10px; border-radius:10px; font-weight:bold; font-size:0.95rem; cursor:pointer;">
                                🛒 ซื้อต่อคอร์ทนี้ทันที
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <div style="text-align:center; padding:50px 20px; background:#fff; border-radius:16px; border:1px dashed #cbd5e1; margin-top:10px;">
        <p style="font-size:1.1rem; color:var(--text-muted); margin:0;">ยังไม่มีรายการส่งต่อคอร์ทหลุดในขณะนี้</p>
    </div>
<?php endif; ?>

<?php page_end(); ?>