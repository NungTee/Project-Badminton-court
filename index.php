<?php
// index.php
date_default_timezone_set('Asia/Bangkok');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/crud_bootstrap.php';

// เคลียร์ Hold ที่หมดอายุ
mysqli_query($conn, "DELETE FROM slot_holds WHERE expires_at <= NOW()");

// รับค่าจากตัวกรอง (Filter)
$selectedDate = $_GET['slot_date'] ?? date('Y-m-d');
$selectedType = $_GET['court_type'] ?? '';

// ดึงคอร์ททั้งหมดตามประเภทที่เลือก
$courtSql = "SELECT * FROM courts WHERE is_active = 1";
if (!empty($selectedType)) {
    $courtSql .= " AND court_type = '" . mysqli_real_escape_string($conn, $selectedType) . "'";
}
$courtSql .= " ORDER BY id ASC";
$courtsRes = mysqli_query($conn, $courtSql);

$courts = [];
while ($c = mysqli_fetch_assoc($courtsRes)) {
    $courts[] = $c;
}

// ดึงสล็อตเวลาและสถานะการ Hold ตามวันที่เลือก
$slotsSql = "
    SELECT cs.*, sh.id as hold_id, sh.expires_at 
    FROM court_slots cs
    LEFT JOIN slot_holds sh ON cs.id = sh.court_slot_id AND sh.expires_at > NOW()
    WHERE cs.slot_date = ?
    ORDER BY cs.start_time ASC
";
$stmt = mysqli_prepare($conn, $slotsSql);
mysqli_stmt_bind_param($stmt, "s", $selectedDate);
mysqli_stmt_execute($stmt);
$slotsRes = mysqli_stmt_get_result($stmt);

// จัดกลุ่มสล็อตเวลา [start_time][court_id]
$grid = [];
$timeList = [];

while ($s = mysqli_fetch_assoc($slotsRes)) {
    $timeKey = substr($s['start_time'], 0, 5) . ' น.';
    if (!in_array($timeKey, $timeList)) {
        $timeList[] = $timeKey;
    }
    $grid[$timeKey][$s['court_id']] = $s;
}

page_start("เลือกเวลาจองสนาม");
?>

<!-- 1. FLOATING CONTROL BAR (แถบเลือกวันที่และตัวกรอง) -->
<form method="get" action="index.php" class="filter-bar">
    <div class="filter-group">
        <label for="slot_date">📅 เลือกวันที่:</label>
        <input type="date" id="slot_date" name="slot_date" class="filter-control" value="<?= h($selectedDate) ?>" onchange="this.form.submit()">
    </div>

    <div class="filter-group">
        <label for="court_type">🏸 ประเภทพื้นคอร์ท:</label>
        <select id="court_type" name="court_type" class="filter-control" onchange="this.form.submit()">
            <option value="">ทั้งหมด</option>
            <option value="RUBBER" <?= $selectedType === 'RUBBER' ? 'selected' : '' ?>>พื้นยาง PBSI</option>
            <option value="WOOD" <?= $selectedType === 'WOOD' ? 'selected' : '' ?>>พื้นไม้ปาร์เก้</option>
        </select>
    </div>
</form>

<!-- 2. STATUS LEGENDS (คำอธิบายสถานะ) -->
<div class="status-legend">
    <span class="badge-status badge-available">🟢 ว่าง (กดจองได้)</span>
    <span class="badge-status badge-holding">🟠 ติด Hold (กำลังเลือก)</span>
    <span class="badge-status badge-booked">🔴 จองแล้ว</span>
</div>

<!-- 3. SPORT GRID TABLE (ตารางคอร์ทและเวลาจอง) -->
<?php if (!empty($courts) && !empty($timeList)): ?>
    <table class="grid-table">
        <thead>
            <tr>
                <th style="width: 130px;">เวลา</th>
                <?php foreach ($courts as $c): ?>
                    <th>
                        <?= h($c['court_name']) ?>
                        <span class="badge-type"><?= $c['court_type'] === 'RUBBER' ? 'ยาง PBSI' : 'ไม้ปาร์เก้' ?></span>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($timeList as $timeStr): ?>
                <tr>
                    <td class="time-col"><?= h($timeStr) ?></td>
                    <?php foreach ($courts as $c): ?>
                        <td>
                            <?php 
                            $slot = $grid[$timeStr][$c['id']] ?? null;
                            if ($slot): 
                                $isAvailable = (int)$slot['is_available'];
                                $isHolding = !empty($slot['hold_id']);
                            ?>
                                <?php if ($isAvailable && !$isHolding): ?>
                                    <!-- ปุ่มว่าง สามารถกดจองได้ -->
                                    <form method="post" action="booking_hold.php" style="margin:0;">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="court_slot_id" value="<?= h($slot['id']) ?>">
                                        <button type="submit" class="btn-slot available">
                                            ฿<?= h(number_format($slot['price'])) ?>
                                        </button>
                                    </form>
                                <?php elseif ($isHolding): ?>
                                    <!-- สถานะติด Hold -->
                                    <div class="btn-slot holding">
                                        ติด Hold
                                    </div>
                                <?php else: ?>
                                    <!-- สถานะเต็ม/จองแล้ว -->
                                    <div class="btn-slot booked">
                                        เต็ม
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:#cbd5e1;">-</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <div style="text-align:center; padding:50px 20px; background:#f8fafc; border-radius:16px; border:1px dashed #cbd5e1;">
        <p style="font-size:1.1rem; color:#64748b; margin:0;">ไม่พบสล็อตเวลาสนามสำหรับวันที่เลือก กรุณาเลือกวันที่อื่นหรือให้แอดมินสร้างสล็อตเวลา</p>
    </div>
<?php endif; ?>

<?php page_end(); ?>