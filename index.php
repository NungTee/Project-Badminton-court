<?php
// index.php
require_once __DIR__ . '/crud_bootstrap.php';

$selected_date = $_GET['date'] ?? date('Y-m-d');

$courts = [];
$res = mysqli_query($conn, "SELECT * FROM courts WHERE is_active = 1 ORDER BY id ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $courts[] = $row;
}

$slots = [];
$stmt = mysqli_prepare($conn, "
    SELECT cs.*, 
           (SELECT COUNT(*) FROM slot_holds sh WHERE sh.court_slot_id = cs.id AND sh.expires_at > NOW()) as is_held
    FROM court_slots cs 
    WHERE cs.slot_date = ? 
    ORDER BY cs.start_time ASC, cs.court_id ASC
");
mysqli_stmt_bind_param($stmt, "s", $selected_date);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $slots[$row['start_time']][$row['court_id']] = $row;
}
mysqli_stmt_close($stmt);

page_start("เลือกเวลาจองสนาม");
?>

<div class="date-picker-box">
    <form method="get" action="index.php">
        <label for="date">เลือกวันที่เล่น:</label>
        <input type="date" id="date" name="date" value="<?= h($selected_date) ?>" min="<?= date('Y-m-d') ?>" onchange="this.form.submit()">
    </form>
</div>

<div class="legend">
    <span class="badge available">ว่าง</span>
    <span class="badge holding">กำลังมีคนเลือก (Hold)</span>
    <span class="badge booked">จองแล้ว</span>
</div>

<table class="grid-table">
    <thead>
        <tr>
            <th>เวลา</th>
            <?php foreach ($courts as $court): ?>
                <th><?= h($court['court_name']) ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($slots as $time => $court_slots): ?>
            <tr>
                <td><strong><?= h(substr($time, 0, 5)) ?> น.</strong></td>
                <?php foreach ($courts as $court): ?>
                    <?php $slot = $court_slots[$court['id']] ?? null; ?>
                    <td>
                        <?php if ($slot): ?>
                            <?php if (!$slot['is_available']): ?>
                                <span class="slot-btn booked">เต็ม</span>
                            <?php elseif ($slot['is_held'] > 0): ?>
                                <span class="slot-btn holding">ติดจอง</span>
                            <?php else: ?>
                                <form method="post" action="booking_hold.php">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="court_slot_id" value="<?= h($slot['id']) ?>">
                                    <button type="submit" class="slot-btn available">
                                        ฿<?= h(number_format($slot['price'])) ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="slot-btn disabled">-</span>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php page_end(); ?>