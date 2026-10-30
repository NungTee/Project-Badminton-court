<?php
// admin_dashboard.php
require_once __DIR__ . '/crud_bootstrap.php';

if (($_SESSION['role'] ?? '') !== 'OWNER') {
    exit('Unauthorized');
}

$total_income = 0;
$res = mysqli_query($conn, "SELECT SUM(amount) as total FROM payments WHERE status = 'SUCCESS'");
if ($row = mysqli_fetch_assoc($res)) {
    $total_income = $row['total'] ?? 0;
}

$status_counts = [];
$res = mysqli_query($conn, "SELECT status, COUNT(*) as count FROM bookings GROUP BY status");
while ($row = mysqli_fetch_assoc($res)) {
    $status_counts[$row['status']] = $row['count'];
}

$pop_times = mysqli_query($conn, "
    SELECT cs.start_time, cs.end_time, COUNT(b.id) as total_booked
    FROM bookings b
    JOIN court_slots cs ON b.court_slot_id = cs.id
    GROUP BY cs.start_time, cs.end_time
    ORDER BY total_booked DESC
    LIMIT 3
");

page_start("แดชบอร์ดสรุปภาพรวม (Admin)");
?>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:20px; margin-bottom:30px;">
    <div style="background:#e8f5e9; padding:20px; border-radius:8px; border:1px solid #a5d6a7;">
        <h3 style="margin:0; color:#2e7d32;">💰 รายได้รวมจำลอง</h3>
        <p style="font-size:1.8rem; font-weight:bold; margin:10px 0; color:#1b5e20;">฿<?= number_format($total_income, 2) ?></p>
    </div>
    <div style="background:#e3f2fd; padding:20px; border-radius:8px; border:1px solid #90caf9;">
        <h3 style="margin:0; color:#1565c0;">🏸 จำนวนการจองสำเร็จ</h3>
        <p style="font-size:1.8rem; font-weight:bold; margin:10px 0; color:#0d47a1;"><?= number_format($status_counts['CONFIRMED'] ?? 0) ?> รายการ</p>
    </div>
    <div style="background:#fff3e0; padding:20px; border-radius:8px; border:1px solid #ffcc80;">
        <h3 style="margin:0; color:#ef6c00;">🔄 อยู่ในตลาดคอร์ทหลุด</h3>
        <p style="font-size:1.8rem; font-weight:bold; margin:10px 0; color:#e65100;"><?= number_format($status_counts['FOR_RESALE'] ?? 0) ?> รายการ</p>
    </div>
</div>

<h2>🔥 3 อันดับช่วงเวลาที่มีการจองมากที่สุด</h2>
<table class="data-table" style="width:100%; border-collapse:collapse; text-align:center;">
    <thead>
        <tr style="background:#f0f4f8;">
            <th style="padding:10px; border:1px solid #ddd;">ช่วงเวลา</th>
            <th style="padding:10px; border:1px solid #ddd;">จำนวนครั้งที่ถูกจอง</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($pt = mysqli_fetch_assoc($pop_times)): ?>
            <tr>
                <td style="padding:10px; border:1px solid #ddd;"><?= h(substr($pt['start_time'], 0, 5)) ?> - <?= h(substr($pt['end_time'], 0, 5)) ?> น.</td>
                <td style="padding:10px; border:1px solid #ddd; font-weight:bold; color:#2e7d32;"><?= h($pt['total_booked']) ?> ครั้ง</td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php page_end(); ?>