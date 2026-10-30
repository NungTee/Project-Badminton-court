<?php
// resale_market.php
require_once __DIR__ . '/crud_bootstrap.php';

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'list_resale') {
    verify_csrf();
    $bookingId = valid_id($_POST['booking_id'] ?? null);

    if ($bookingId) {
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
            }
        } catch (Throwable $e) {
            mysqli_rollback($conn);
        }
    }
}

$sql = "
    SELECT r.id as resale_id, r.resale_price, cs.slot_date, cs.start_time, cs.end_time, c.court_name, v.name as venue_name, u.full_name as seller_name
    FROM resale_slots r
    JOIN bookings b ON r.booking_id = b.id
    JOIN court_slots cs ON b.court_slot_id = cs.id
    JOIN courts c ON cs.court_id = c.id
    JOIN venues v ON c.venue_id = v.id
    JOIN badminton_users u ON r.seller_id = u.id
    WHERE r.status = 'OPEN' AND cs.slot_date >= CURDATE()
    ORDER BY cs.slot_date ASC, cs.start_time ASC
";
$resales = mysqli_query($conn, $sql);

page_start("ตลาดคอร์ทหลุด / ปล่อยคิวต่อ");
if (isset($_GET['success'])) echo '<p class="ok">นำคอร์ทลงตลาดปล่อยคิวต่อเรียบร้อยแล้ว!</p>';
?>

<div class="resale-list">
    <?php while ($r = mysqli_fetch_assoc($resales)): ?>
        <div class="card resale-card">
            <h3><?= h($r['venue_name']) ?> (<?= h($r['court_name']) ?>)</h3>
            <p>📅 วันที่: <?= h($r['slot_date']) ?></p>
            <p>⏰ เวลา: <?= h(substr($r['start_time'], 0, 5)) ?> - <?= h(substr($r['end_time'], 0, 5)) ?> น.</p>
            <p>👤 ปล่อยโดย: <?= h($r['seller_name']) ?></p>
            <p class="price-tag">ราคาปล่อยต่อ: <strong>฿<?= h(number_format($r['resale_price'])) ?></strong></p>
        </div>
    <?php endwhile; ?>
</div>

<?php page_end(); ?>