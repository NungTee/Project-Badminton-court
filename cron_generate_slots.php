<?php
// cron_generate_slots.php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/config/db.php';

// สร้างสำหรับวันที่ล่วงหน้า 7 วัน
$targetDate = date('Y-m-d', strtotime('+7 days'));
$defaultPrice = 200.00;

$courtsRes = mysqli_query($conn, "SELECT id FROM courts WHERE is_active = 1");
$courtIds = [];
while ($c = mysqli_fetch_assoc($courtsRes)) {
    $courtIds[] = $c['id'];
}

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

$sql = "INSERT IGNORE INTO court_slots (court_id, slot_date, start_time, end_time, price, is_available) VALUES (?, ?, ?, ?, ?, 1)";
$stmt = mysqli_prepare($conn, $sql);

foreach ($courtIds as $cId) {
    foreach ($timeSlots as $ts) {
        mysqli_stmt_bind_param($stmt, "isssd", $cId, $targetDate, $ts[0], $ts[1], $defaultPrice);
        mysqli_stmt_execute($stmt);
    }
}
mysqli_stmt_close($stmt);

echo "Auto generate slots for $targetDate completed.";