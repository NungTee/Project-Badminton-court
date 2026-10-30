<?php
// config/db.php
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 's68160383';
$pass = getenv('DB_PASS') ?: '';
$dbname = getenv('DB_NAME') ?: 's68160383';

$conn = mysqli_connect($host, $user, $pass, $dbname);
if (!$conn) {
    error_log(mysqli_connect_error());
    http_response_code(500);
    exit('Database unavailable');
}
mysqli_set_charset($conn, 'utf8mb4');