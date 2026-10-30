<?php
// helpers.php
function send_line_notify($message, $conn) {
    $res = mysqli_query($conn, "SELECT setting_value FROM system_settings WHERE setting_key = 'line_notify_token'");
    $row = mysqli_fetch_assoc($res);
    $token = $row['setting_value'] ?? '';

    if (empty($token)) {
        return false; // ไม่ได้ตั้งค่า Token ไว้
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://notify-api.line.me/api/notify");
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "message=" . urlencode($message));
    $headers = [
        'Content-Type: application/x-www-form-urlencoded',
        'Authorization: Bearer ' . $token
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}