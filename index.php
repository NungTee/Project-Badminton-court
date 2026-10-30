<?php
// index.php
require_once __DIR__ . '/crud_bootstrap.php';

$selected_date = $_GET['date'] ?? date('Y-m-d');
$court_type = $_GET['court_type'] ?? 'ALL';

// ดึงคอร์ทที่เปิดใช้งานพร้อม Filter
$court_query = "SELECT * FROM courts WHERE is_active = 1";
if ($court_type !== 'ALL') {
    $court_query .= " AND court_type = '" . mysqli_real_escape_string($conn, $court_type) . "'";
}
$court_query .= " ORDER BY id ASC";

$courts = [];
$res = mysqli_query($conn, $court_query);
while ($row = mysqli_fetch_assoc($res)) {
    $courts[] = $row;
}

// ดึงสล็อตเวลา
$slots = [];
$stmt = mysqli_prepare($conn, "
    SELECT cs.*, 
           (SELECT COUNT(*) FROM slot_holds sh WHERE sh.court_slot_id = cs.id AND sh.expires_at > NOW()) as is_held
    FROM court_slots cs 
    WHERE cs.slot_date = ? 
    ORDER BY cs.start_time ASC, cs.court_id ASC
");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $selected_date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $slots[$row['start_time']][$row['court_id']] = $row;
    }
    mysqli_stmt_close($stmt);
}

page_start("เลือกเวลาจองสนาม");
?>

<!-- Form กรองข้อมูล -->
<div class="filter-box" style="background:#f9f9f9; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #ddd; display:flex; gap:20px; align-items:center; flex-wrap:wrap;">
    <form method="get" action="index.php" style="display:flex; gap:15px; align-items:center; margin:0;">
        <div>
            <label for="date"><strong>📅 เลือกวันที่:</strong> </label>
            <input type="date" id="date" name="date" value="<?= h($selected_date) ?>" min="<?= date('Y-m-d') ?>" onchange="this.form.submit()">
        </div>
        <div>
            <label for="court_type"><strong>🏸 ประเภทพื้นคอร์ท:</strong> </label>
            <select name="court_type" id="court_type" onchange="this.form.submit()" style="padding:5px;">
                <option value="ALL" <?= $court_type === 'ALL' ? 'selected' : '' ?>>ทั้งหมด</option>
                <option value="RUBBER" <?= $court_type === 'RUBBER' ? 'selected' : '' ?>>ยาง (PBSI)</option>
                <option value="WOOD" <?= $court_type === 'WOOD' ? 'selected' : '' ?>>ไม้ปาร์เก้</option>
            </select>
        </div>
    </form>
</div>

<div class="legend" style="display:flex; gap:15px; margin-bottom:15px;">
    <span class="badge available" style="background:#e8f5e9; color:#2e7d32; padding:4px 8px; border-radius:4px;">ว่าง</span>
    <span class="badge holding" style="background:#fff3e0; color:#ef6c00; padding:4px 8px; border-radius:4px;">ติด Hold</span>
    <span class="badge booked" style="background:#ffebee; color:#c62828; padding:4px 8px; border-radius:4px;">จองแล้ว</span>
</div>

<?php if (empty($slots) || empty($courts)): ?>
    <div class="card" style="padding:20px; background:#fff; border:1px solid #ddd; border-radius:8px; text-align:center;">
        <p class="err" style="color:#d32f2f;">ไม่พบสล็อตเวลาสำหรับเงื่อนไขที่เลือก (วันที่ <?= h($selected_date) ?>)</p>
    </div>
<?php else: ?>
    <table class="grid-table" style="width:100%; border-collapse:collapse; text-align:center;">
        <thead>
            <tr style="background:#f0f4f8;">
                <th style="padding:10px; border:1px solid #ddd;">เวลา</th>
                <?php foreach ($courts as $court): ?>
                    <th style="padding:10px; border:1px solid #ddd;"><?= h($court['court_name']) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($slots as $time => $court_slots): ?>
                <tr>
                    <td style="padding:10px; border:1px solid #ddd;"><strong><?= h(substr($time, 0, 5)) ?> น.</strong></td>
                    <?php foreach ($courts as $court): ?>
                        <?php $slot = $court_slots[$court['id']] ?? null; ?>
                        <td style="padding:8px; border:1px solid #ddd;">
                            <?php if ($slot): ?>
                                <?php if (!$slot['is_available']): ?>
                                    <span class="slot-btn booked" style="display:block; padding:8px; background:#ffebee; color:#c62828; border-radius:4px;">เต็ม</span>
                                <?php elseif ($slot['is_held'] > 0): ?>
                                    <span class="slot-btn holding" style="display:block; padding:8px; background:#fff3e0; color:#ef6c00; border-radius:4px;">ติดจอง</span>
                                <?php else: ?>
                                    <form method="post" action="booking_hold.php">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="court_slot_id" value="<?= h($slot['id']) ?>">
                                        <button type="submit" class="slot-btn available" style="width:100%; padding:8px; background:#e8f5e9; color:#2e7d32; border:1px solid #a5d6a7; border-radius:4px; font-weight:bold; cursor:pointer;">
                                            ฿<?= h(number_format($slot['price'])) ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:#aaa;">-</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php page_end(); ?>