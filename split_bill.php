<?php
// split_bill.php
date_default_timezone_set('Asia/Bangkok');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/crud_bootstrap.php';

$bookingId = valid_id($_GET['booking_id'] ?? $_POST['booking_id'] ?? null);
$userId = (int)($_SESSION['user_id'] ?? 0);
$userName = $_SESSION['full_name'] ?? 'ผู้ใช้งานทั่วไป';

if (!$bookingId || !$userId) {
    header('Location: my_bookings.php');
    exit;
}

$msg = '';
$err = '';

// 1. ดึงข้อมูลการจอง
$sql = "
    SELECT b.id as booking_id, b.total_price, b.user_id as host_id,
           cs.slot_date, cs.start_time, cs.end_time, c.court_name, v.name as venue_name
    FROM bookings b
    JOIN court_slots cs ON b.court_slot_id = cs.id
    JOIN courts c ON cs.court_id = c.id
    JOIN venues v ON c.venue_id = v.id
    WHERE b.id = ?
    LIMIT 1
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $bookingId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$booking = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$booking) {
    page_start("ไม่พบรายการจอง");
    echo '<p class="err">ไม่พบข้อมูลการจองนี้</p>';
    page_end();
    exit;
}

// 2. ดึงข้อมูลการหารบิล (ถ้ามีแล้ว)
$splitStmt = mysqli_prepare($conn, "SELECT * FROM booking_splits WHERE booking_id = ? LIMIT 1");
mysqli_stmt_bind_param($splitStmt, "i", $bookingId);
mysqli_stmt_execute($splitStmt);
$splitRes = mysqli_stmt_get_result($splitStmt);
$split = mysqli_fetch_assoc($splitRes);
mysqli_stmt_close($splitStmt);

// 3. ประมวลผล: สร้างการหารบิลใหม่ (เฉพาะเจ้าของบิล)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_split') {
    verify_csrf();
    $splitCount = (int)($_POST['split_count'] ?? 1);

    if ($booking['host_id'] !== $userId) {
        $err = 'เฉพาะผู้จองสนามเท่านั้นที่สามารถตั้งค่าการหารบิลได้';
    } elseif ($splitCount < 2) {
        $err = 'จำนวนคนหารต้องอย่างน้อย 2 คนขึ้นไป';
    } else {
        $totalAmount = $booking['total_price'];
        $perPerson = round($totalAmount / $splitCount, 2);

        mysqli_begin_transaction($conn);
        try {
            // สร้างบิลหลัก
            $insSplit = mysqli_prepare($conn, "INSERT INTO booking_splits (booking_id, host_user_id, total_amount, split_count, per_person_amount) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($insSplit, "iidid", $bookingId, $userId, $totalAmount, $splitCount, $perPerson);
            mysqli_stmt_execute($insSplit);
            $splitId = mysqli_insert_id($conn);
            mysqli_stmt_close($insSplit);

            // สร้างช่องให้ Host (ถือว่าจ่ายส่วนของตัวเองแล้ว)
            $insItem = mysqli_prepare($conn, "INSERT INTO booking_split_items (split_id, user_id, payer_name, amount, payment_status, paid_at) VALUES (?, ?, ?, ?, 'PAID', NOW())");
            mysqli_stmt_bind_param($insItem, "iisd", $splitId, $userId, $userName, $perPerson);
            mysqli_stmt_execute($insItem);
            mysqli_stmt_close($insItem);

            // สร้างช่องรอชำระของสมาชิกคนอื่นๆ
            for ($i = 2; $i <= $splitCount; $i++) {
                $payerLabel = "เพื่อนคนที่ $i";
                $insMember = mysqli_prepare($conn, "INSERT INTO booking_split_items (split_id, payer_name, amount, payment_status) VALUES (?, ?, ?, 'PENDING')");
                mysqli_stmt_bind_param($insMember, "isd", $splitId, $payerLabel, $perPerson);
                mysqli_stmt_execute($insMember);
                mysqli_stmt_close($insMember);
            }

            mysqli_commit($conn);
            header("Location: split_bill.php?booking_id=" . $bookingId);
            exit;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $err = $e->getMessage();
        }
    }
}

// 4. ประมวลผล: เพื่อนกดชำระเงินส่วนของตัวเอง
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay_split_item') {
    verify_csrf();
    $itemId = valid_id($_POST['item_id'] ?? null);
    $payerNameInput = trim($_POST['payer_name'] ?? '');

    if ($itemId && $split) {
        $payerName = !empty($payerNameInput) ? $payerNameInput : $userName;
        $upItem = mysqli_prepare($conn, "UPDATE booking_split_items SET user_id = ?, payer_name = ?, payment_status = 'PAID', paid_at = NOW() WHERE id = ? AND split_id = ? AND payment_status = 'PENDING'");
        mysqli_stmt_bind_param($upItem, "isii", $userId, $payerName, $itemId, $split['id']);
        mysqli_stmt_execute($upItem);
        mysqli_stmt_close($upItem);

        // เช็กว่าจ่ายครบทุกคนหรือยัง
        $checkPending = mysqli_query($conn, "SELECT COUNT(*) as pending_count FROM booking_split_items WHERE split_id = {$split['id']} AND payment_status = 'PENDING'");
        $pendingRow = mysqli_fetch_assoc($checkPending);
        if ($pendingRow['pending_count'] == 0) {
            mysqli_query($conn, "UPDATE booking_splits SET status = 'COMPLETED' WHERE id = {$split['id']}");
        }

        $msg = "ชำระส่วนของคุณเรียบร้อยแล้ว!";
        header("Location: split_bill.php?booking_id=" . $bookingId);
        exit;
    }
}

// 5. ดึงรายการเพื่อนที่หารบิล
$splitItems = [];
if ($split) {
    $itemsRes = mysqli_query($conn, "SELECT * FROM booking_split_items WHERE split_id = {$split['id']} ORDER BY id ASC");
    while ($r = mysqli_fetch_assoc($itemsRes)) {
        $splitItems[] = $r;
    }
}

$shareUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

page_start("หารค่าสนามกับเพื่อน (Split Bill)");
?>

<?php if ($msg): ?><div class="ok">✅ <?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="err">⚠️ <?= h($err) ?></div><?php endif; ?>

<div style="max-width:650px; margin:0 auto;">
    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:16px; padding:20px; margin-bottom:25px;">
        <h3 style="margin-top:0; color:#0f172a;">🏸 รายละเอียดการจองสนาม</h3>
        <p style="margin:5px 0;"><strong>สนาม:</strong> <?= h($booking['venue_name']) ?> (<?= h($booking['court_name']) ?>)</p>
        <p style="margin:5px 0;"><strong>วันที่-เวลา:</strong> <?= h($booking['slot_date']) ?> | <?= h(substr($booking['start_time'],0,5)) ?> - <?= h(substr($booking['end_time'],0,5)) ?> น.</p>
        <p style="margin:5px 0; font-size:1.1rem; color:#047857; font-weight:bold;">ยอดรวมทั้งสิ้น: ฿<?= number_format($booking['total_price'], 2) ?></p>
    </div>

    <?php if (!$split): ?>
        <!-- ฟอร์มสร้างบิลสำหรับ Host -->
        <?php if ($booking['host_id'] === $userId): ?>
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:25px; text-align:center;">
                <h3 style="margin-top:0; color:#0f172a;">🍕 ตั้งค่าหารค่าสนามกับเพื่อน</h3>
                <p style="color:#64748b; font-size:0.95rem;">เลือกจำนวนคนที่ต้องการหาร ระบบจะคำนวณยอดต่อคนและสร้างลิงก์สำหรับแชร์ให้เพื่อนทันที</p>
                
                <form method="post" style="margin-top:20px;">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="create_split">
                    <input type="hidden" name="booking_id" value="<?= h($bookingId) ?>">

                    <div style="margin-bottom:20px;">
                        <label style="font-weight:bold; display:block; margin-bottom:8px;">จำนวนคนที่จะหาร (รวมตัวคุณ):</label>
                        <input type="number" name="split_count" value="4" min="2" max="20" style="width:100px; padding:10px; font-size:1.1rem; text-align:center; border:1px solid #cbd5e1; border-radius:10px;">
                    </div>

                    <button type="submit" style="background:#0f172a; color:white; border:none; padding:12px 28px; border-radius:30px; font-weight:bold; font-size:1rem; cursor:pointer;">
                        🚀 สร้างกลุ่มหารบิล
                    </button>
                </form>
            </div>
        <?php else: ?>
            <p style="text-align:center; color:#64748b; padding:30px;">เจ้าของรายการจองยังไม่ได้ตั้งค่าการหารบิล</p>
        <?php endif; ?>

    <?php else: ?>
        <!-- หน้าแสดงรายการกลุ่มหารบิล -->
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:25px; margin-bottom:25px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid #e2e8f0; padding-bottom:12px;">
                <h3 style="margin:0; color:#0f172a;">👥 สมาชิกหารบิล (ตกคนละ ฿<?= number_format($split['per_person_amount'], 2) ?>)</h3>
                <span class="badge-status <?= $split['status'] === 'COMPLETED' ? 'badge-available' : 'badge-holding' ?>">
                    <?= $split['status'] === 'COMPLETED' ? '✅ จ่ายครบทุกคนแล้ว' : '⏳ รอจ่ายเงิน' ?>
                </span>
            </div>

            <!-- รายชื่อคนหาร -->
            <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:25px;">
                <?php foreach ($splitItems as $idx => $item): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; background:#f8fafc; padding:12px 16px; border-radius:12px; border:1px solid #f1f5f9;">
                        <div>
                            <strong style="color:#0f172a;"><?= h($item['payer_name']) ?></strong>
                            <?php if ($item['user_id'] === $booking['host_id']): ?>
                                <span style="font-size:0.75rem; background:#e2e8f0; color:#475569; padding:2px 6px; border-radius:4px; margin-left:6px;">ผู้สร้างรายการ</span>
                            <?php endif; ?>
                            <div style="font-size:0.85rem; color:#64748b; margin-top:2px;">฿<?= number_format($item['amount'], 2) ?></div>
                        </div>

                        <div>
                            <?php if ($item['payment_status'] === 'PAID'): ?>
                                <span style="color:#047857; font-weight:bold; font-size:0.9rem;">✅ ชำระแล้ว</span>
                            <?php else: ?>
                                <form method="post" style="margin:0;">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="pay_split_item">
                                    <input type="hidden" name="item_id" value="<?= h($item['id']) ?>">
                                    <input type="hidden" name="booking_id" value="<?= h($bookingId) ?>">
                                    <input type="hidden" name="payer_name" value="<?= h($userName) ?>">
                                    <button type="submit" style="background:#047857; color:white; border:none; padding:6px 14px; border-radius:20px; font-weight:bold; font-size:0.85rem; cursor:pointer;">
                                        💳 กดชำระส่วนนี้
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- กล่องแชร์ลิงก์ให้เพื่อน -->
            <div style="background:#eff6ff; border:1px solid #bfdbfe; padding:15px; border-radius:12px; text-align:center;">
                <label style="font-weight:bold; color:#1e40af; display:block; margin-bottom:6px;">🔗 ส่งลิงก์นี้ให้เพื่อนสแกน/กดเข้ามาหารเงิน:</label>
                <input type="text" value="<?= h($shareUrl) ?>" readonly style="width:100%; padding:8px; border:1px solid #93c5fd; border-radius:8px; background:#fff; text-align:center; font-size:0.88rem; box-sizing:border-box;">
            </div>
        </div>
    <?php endif; ?>

    <div style="text-align:center;">
        <a href="my_bookings.php" style="color:#64748b; text-decoration:none;">← กลับสู่รายการจองของฉัน</a>
    </div>
</div>

<?php page_end(); ?>