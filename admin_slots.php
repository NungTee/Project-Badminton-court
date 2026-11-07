<?php
// admin_slots.php
date_default_timezone_set('Asia/Bangkok');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/crud_bootstrap.php';

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

// 1. ระบบสร้างสล็อตเวลาอัตโนมัติ (13:00 - 21:00 น. สล็อตละ 1 ชั่วโมง)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'auto_generate') {
    verify_csrf();

    $startDate = $_POST['start_date'] ?? date('Y-m-d');
    $endDate = $_POST['end_date'] ?? date('Y-m-d');
    $defaultPrice = filter_var($_POST['default_price'] ?? 200, FILTER_VALIDATE_FLOAT);

    if ($startDate > $endDate) {
        $error = 'วันที่เริ่มต้นต้องไม่มากกว่าวันที่สิ้นสุด';
    } else {
        // ดึงคอร์ททั้งหมดที่เปิดใช้งาน
        $courtsRes = mysqli_query($conn, "SELECT id FROM courts WHERE is_active = 1");
        $courtIds = [];
        while ($c = mysqli_fetch_assoc($courtsRes)) {
            $courtIds[] = $c['id'];
        }

        // รายการช่วงเวลา 13:00 - 21:00 น. (สล็อตละ 1 ชั่วโมง)
        $timeSlots = [
            ['13:00:00', '14:00:00'],
            ['14:00:00', '15:00:00'],
            ['15:00:00', '16:00:00'],
            ['16:00:00', '17:00:00'],
            ['17:00:00', '18:00:00'],
            ['18:00:00', '19:00:00'],
            ['19:00:00', '20:00:00'],
            ['20:00:00', '21:00:00'],
        ];

        $createdCount = 0;
        $currentTimestamp = strtotime($startDate);
        $endTimestamp = strtotime($endDate);

        $sql = "INSERT INTO court_slots (court_id, slot_date, start_time, end_time, price, is_available) 
                VALUES (?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE price = VALUES(price)";
        $stmt = mysqli_prepare($conn, $sql);

        while ($currentTimestamp <= $endTimestamp) {
            $dateStr = date('Y-m-d', $currentTimestamp);

            foreach ($courtIds as $cId) {
                foreach ($timeSlots as $ts) {
                    mysqli_stmt_bind_param($stmt, "isssd", $cId, $dateStr, $ts[0], $ts[1], $defaultPrice);
                    if (mysqli_stmt_execute($stmt)) {
                        $createdCount++;
                    }
                }
            }
            $currentTimestamp = strtotime('+1 day', $currentTimestamp);
        }
        mysqli_stmt_close($stmt);

        $message = "สร้างสล็อตเวลาอัตโนมัติสำเร็จ! รวมทั้งหมด $createdCount สล็อตช่วงเวลา (13:00 - 21:00 น.)";
    }
}

// 2. เพิ่มสล็อตเวลาแบบทีละรายการ (Manual)
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

// 3. ลบสล็อตเวลา
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

$courts = [];
$res = mysqli_query($conn, "SELECT * FROM courts WHERE is_active = 1 ORDER BY id ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $courts[] = $row;
}

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

<!-- กล่องสร้างสล็อตเวลาอัตโนมัติ -->
<div class="panel" style="background:#e3f2fd; padding:20px; border-radius:8px; margin-bottom:20px; border:1px solid #90caf9;">
    <h2 style="margin-top:0; color:#1565c0;">⚡ สร้างสล็อตเวลาอัตโนมัติ (13:00 - 21:00 น.)</h2>
    <p style="color:#555; font-size:0.9rem; margin-bottom:15px;">ระบบจะสร้างสล็อตละ 1 ชั่วโมง (13-14, 14-15, 15-16, 16-17, 17-18, 18-19, 19-20, 20-21) ให้กับทุกคอร์ทโดยอัตโนมัติ</p>
    
    <form method="post" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:15px;">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="auto_generate">

        <div>
            <label style="display:block; font-weight:bold; margin-bottom:5px;">ตั้งแต่วันที่:</label>
            <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required style="width:100%; padding:8px; border-radius:4px; border:1px solid #ccc;">
        </div>

        <div>
            <label style="display:block; font-weight:bold; margin-bottom:5px;">ถึงวันที่:</label>
            <input type="date" name="end_date" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" min="<?= date('Y-m-d') ?>" required style="width:100%; padding:8px; border-radius:4px; border:1px solid #ccc;">
        </div>

        <div>
            <label style="display:block; font-weight:bold; margin-bottom:5px;">ราคาต่อชั่วโมง (บาท):</label>
            <input type="number" name="default_price" value="200" step="0.01" required style="width:100%; padding:8px; border-radius:4px; border:1px solid #ccc;">
        </div>

        <div style="grid-column: 1 / -1;">
            <button type="submit" style="padding:10px 20px; background:#1565c0; color:white; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">
                🚀 กดสร้างสล็อตเวลาอัตโนมัติทันที
            </button>
        </div>
    </form>
</div>

<!-- กล่องเพิ่มสล็อตเวลาแบบระบุเอง -->
<div class="panel" style="background:#f9f9f9; padding:20px; border-radius:8px; margin-bottom:30px; border:1px solid #ddd;">
    <h2 style="margin-top:0;">➕ เพิ่มสล็อตเวลาแบบระบุเอง</h2>
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
            <input type="time" name="start_time" value="13:00" required style="width:100%; padding:8px; border-radius:4px; border:1px solid #ccc;">
        </div>

        <div>
            <label style="display:block; font-weight:bold; margin-bottom:5px;">เวลาสิ้นสุด:</label>
            <input type="time" name="end_time" value="14:00" required style="width:100%; padding:8px; border-radius:4px; border:1px solid #ccc;">
        </div>

        <div>
            <label style="display:block; font-weight:bold; margin-bottom:5px;">ราคา (บาท):</label>
            <input type="number" name="price" value="200" step="0.01" required style="width:100%; padding:8px; border-radius:4px; border:1px solid #ccc;">
        </div>

        <div style="grid-column: 1 / -1;">
            <button type="submit" style="padding:10px 20px; background:#2e7d32; color:white; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">
                + เพิ่มสล็อตเวลา
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