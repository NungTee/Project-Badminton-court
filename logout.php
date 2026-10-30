<?php
// logout.php
require_once 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_auth_token(); // ตรวจสอบ CSRF Token ก่อนออกจากระบบ

    // 1. ล้างค่าอาร์เรย์ $_SESSION ทั้งหมด
    $_SESSION = array();

    // 2. ทำลาย Session Cookie ในเบราว์เซอร์
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // 3. ทำลาย Session
    session_destroy();

    // 4. Redirect ไปหน้า login.php
    header('Location: login.php?logged_out=1');
    exit;
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ออกจากระบบ | Badminton Court Booking</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .logout-box { max-width: 400px; margin: 60px auto; text-align: center; }
        .logout-box button { width: 100%; margin-top: 15px; padding: 12px; background: #c62828; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; }
        .logout-box button:hover { background: #b71c1c; }
        .logout-box a { display: inline-block; margin-top: 15px; color: #555; text-decoration: none; }
    </style>
</head>
<body>
<main class="wrap">
    <div class="panel logout-box">
        <h1>🚪 ออกจากระบบ</h1>
        <p>คุณต้องการออกจากระบบใช่หรือไม่?</p>

        <form method="post">
            <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
            <button type="submit">ยืนยันออกจากระบบ</button>
        </form>
        
        <p><a href="index.php">← ยกเลิก และกลับหน้าหลัก</a></p>
    </div>
</main>
</body>
</html>