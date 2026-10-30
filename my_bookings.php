<?php
// my_bookings.php
require_once __DIR__ . '/crud_bootstrap.php';

$userId = (int)$_SESSION['user_id'];

$sql = "
    SELECT b.id as booking_id, b.total_price, b.status, cs.slot_date, cs.start_time, cs.end_time, c.court_name, v.name as venue_name
    FROM bookings b
    JOIN court_slots cs ON b.court_slot_id = cs.id
    JOIN courts c ON cs.court_id = c.id
    JOIN venues v ON c.venue_id = v.id
    WHERE b.user_id = ?
    ORDER BY cs.slot_date DESC, cs.start_time DESC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$bookings = [];
while ($row = mysqli_fetch_assoc($res)) {
    $bookings[] = $row;
}
mysqli_stmt_close($stmt);

page_start("รายการจองของฉัน");
if (isset($_GET['success'])) echo '<p class="ok">ทำรายการสำเร็จ!</p>';
?>

<table class="data-table" style="width:100%; border-collapse:collapse;">
    <thead>
        <tr style="background:#f0f4f8;">
            <th style="padding:10px; border:1px solid #ddd;">สนาม/คอร์ท</th>
            <th style="padding:10px; border:1px solid #ddd;">วันที่</th>
            <th style="padding:10px; border:1px solid #ddd;">เวลา</th>
            <th style="padding:10px; border:1px solid #ddd;">ราคา</th>
            <th style="padding:10px; border:1px solid #ddd;">สถานะ</th>
            <th style="padding:10px; border:1px solid #ddd;">จัดการ</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($bookings as $b): ?>
            <tr style="text-align:center;">
                <td style="padding:10px; border:1px solid #ddd;"><?= h($b['venue_name']) ?> (<?= h($b['court_name']) ?>)</td>
                <td style="padding:10px; border:1px solid #ddd;"><?= h($b['slot_date']) ?></td>
                <td style="padding:10px; border:1px solid #ddd;"><?= h(substr($b['start_time'], 0, 5)) ?> - <?= h(substr($b['end_time'], 0, 5)) ?> น.</td>
                <td style="padding:10px; border:1px solid #ddd;">฿<?= h(number_format($b['total_price'])) ?></td>
                <td style="padding:10px; border:1px solid #ddd;"><?= h($b['status']) ?></td>
                <td style="padding:10px; border:1px solid #ddd;">
                    <?php if ($b['status'] === 'CONFIRMED'): ?>
                        <form method="post" action="resale_market.php">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="booking_id" value="<?= h($b['booking_id']) ?>">
                            <button type="submit" name="action" value="list_resale" class="btn-resale">ปล่อยคิวต่อ</button>
                        </form>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php page_end(); ?>