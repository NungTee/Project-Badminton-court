<?php
// admin_slots.php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/crud_bootstrap.php';

// ตรวจสอบสิทธิ์ผู้ใช้ (ต้องเป็น OWNER เท่านั้น)
if (($_SESSION['role'] ?? '') !== 'OWNER') {
    page_start("ปฏิเสธการเข้าถึง");
    echo '<div class="card" style="padding:20px; text-align:center;">';
    echo '<p class="err" style="color:#c62828; font-size:1.1rem;">⚠️ หน้าต่างนี้สำหรับแอดมินหรือเจ้าของสนามเท่านั้น</p>';
    echo '<p><a href="index.php">← กลับสู่หน้าหลัก</a></p>';
    echo '</div>';
    page_end();
    exit;
}

$message = '';
$error = '';

// 1. เพิ่มสล็อตเวลาใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_slot') {
    verify_csrf();

    $court_id = valid_id($_POST['court_id'] ?? null);
    $slot_date = $_POST['slot_date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $price = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);

    if (!$court_id || !$slot_date || !$start_time || !$end_time || $price === false) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } else {
        $sql = "INSERT INTO court_slots (court_id, slot_date, start_time, end_time, price, is_available) 
                VALUES (?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE price = VALUES(price), is_available = 1";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "isssd", $court_id, $slot_date, $start_time, $end_time, $price);
            if (mysqli_stmt_execute($stmt)) {
                $message = "เพิ่มสล็อตเวลา $start_time - $end_time น. เรียบร้อยแล้ว";
            } else {
                $error = "เกิดข้อผิดพลาด: " . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// 2. ลบสล็อตเวลา
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_slot') {
    verify_csrf();
    $slot_id = valid_id($_POST['slot_id'] ?? null);

    if ($slot_id) {
        $stmt = mysqli_prepare($conn, "DELETE FROM court_slots WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $slot_id);
        if (mysqli_stmt_execute($stmt)) {
            $message = "ลบสล็อตเวลาเรียบร้อยแล้ว";
        } else {
            $error = "ไม่สามารถลบสล็อตได้ (อาจมีการจองค้างอยู่)";
        }
        mysqli_stmt_close($stmt);
    }
}

// ดึงรายการคอร์ททั้งหมด
$courts = [];
$res = mysqli_query($conn, "SELECT * FROM courts WHERE is_active = 1 ORDER BY id ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $courts[] = $row;
}

// ดึงสล็อตเวลาปัจจุบันทั้งหมด
$slots_res = mysqli_query($conn, "
    SELECT cs.*, c.court_name 
    FROM court_slots cs 
    JOIN courts c ON cs.court_id = c.id 
    WHERE cs.slot_date >= CURDATE()
    ORDER BY cs.slot_date ASC, cs.start_time ASC, cs.court_id ASC
");

page_start("จัดการสล็อตเวลาสนาม (แอดมิน)");
?>

<?php if ($message): ?><p class="ok" style="background:#e8f5e9; color:#2e7d32; padding:10px; border-radius:4px; font-weight:bold;"><?= h($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="err" style="background:#ffebee; color:#c62828; padding:10px; border-radius:4px; font-weight:bold;"><?= h($error) ?></p><?php endif; ?>

<div class="panel" style="background:#f9f9f9; padding:20px; border-radius:8px; margin-bottom:30px; border:1px solid #ddd;">
    <h2>➕ เพิ่มสล็อตเวลาใหม่</h2>
    <form method="post" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:15px; margin-top:15px;">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="add_slot">

        <div>
            <label style="display:block; font-weight:bold; margin-bottom:5px;">เลือกคอร์ท:</label>
            <select name="court_id" required style="width:100%; padding:8px; border-radius:4px; border:1px solid #ccc;">
                <?php foreach ($courts as $c): ?>
                    <option value="<?= h($c['id']) ?>"><?= h($c['court_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label style="display:block; font-weight:bold; margin-bottom:5px;">วันที่:</label>
            <input type="date" name="slot_date" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required style="width:100%; padding:8px; border-radius:4px; border:1px solid #ccc;">
        </div>

        <div>
            <label style="display:block; font-weight:bold; margin-bottom:5px;">เวลาเริ่มต้น:</label>
            <input type="time" name="start_time" value="17:00" required style="width:100%; padding:8px; border-radius:4px; border:1px solid #ccc;">
        </div>

        <div>
            <label style="display:block; font-weight:bold; margin-bottom:5px;">เวลาสิ้นสุด:</label>
            <input type="time" name="end_time" value="18:00" required style="width:100%; padding:8px; border-radius:4px; border:1px solid #ccc;">
        </div>

        <div>
            <label style="display:block; font-weight:bold; margin-bottom:5px;">ราคา (บาท):</label>
            <input type="number" name="price" value="200" step="0.01" required style="width:100%; padding:8px; border-radius:4px; border:1px solid #ccc;">
        </div>

        <div style="grid-column: 1 / -1;">
            <button type="submit" style="padding:10px 20px; background:#2e7d32; color:white; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">
                + สร้างสล็อตเวลา
            </button>
        </div>
    </form>
</div>

<h2>📋 รายการสล็อตเวลาทั้งหมด</h2>
<table class="data-table" style="width:100%; border-collapse:collapse; margin-top:15px; text-align:center;">
    <thead>
        <tr style="background:#f0f4f8;">
            <th style="padding:10px; border:1px solid #ddd;">วันที่</th>
            <th style="padding:10px; border:1px solid #ddd;">คอร์ท</th>
            <th style="padding:10px; border:1px solid #ddd;">ช่วงเวลา</th>
            <th style="padding:10px; border:1px solid #ddd;">ราคา</th>
            <th style="padding:10px; border:1px solid #ddd;">สถานะ</th>
            <th style="padding:10px; border:1px solid #ddd;">จัดการ</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($s = mysqli_fetch_assoc($slots_res)): ?>
            <tr>
                <td style="padding:8px; border:1px solid #ddd;"><?= h($s['slot_date']) ?></td>
                <td style="padding:8px; border:1px solid #ddd;"><?= h($s['court_name']) ?></td>
                <td style="padding:8px; border:1px solid #ddd;"><?= h(substr($s['start_time'], 0, 5)) ?> - <?= h(substr($s['end_time'], 0, 5)) ?> น.</td>
                <td style="padding:8px; border:1px solid #ddd;">฿<?= h(number_format($s['price'])) ?></td>
                <td style="padding:8px; border:1px solid #ddd;">
                    <?= $s['is_available'] ? '<span style="color:green;">ว่าง</span>' : '<span style="color:red;">จองแล้ว</span>' ?>
                </td>
                <td style="padding:8px; border:1px solid #ddd;">
                    <form method="post" onsubmit="return confirm('ยืนยันลบสล็อตเวลานี้?');" style="display:inline;">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="delete_slot">
                        <input type="hidden" name="slot_id" value="<?= h($s['id']) ?>">
                        <button type="submit" style="background:#c62828; color:white; border:none; padding:4px 8px; border-radius:4px; cursor:pointer;">ลบ</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php page_end(); ?>