<?php
// register.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config/db.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_auth_token();

    $full_name = trim((string)($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone = trim((string)($_POST['phone_number'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (mb_strlen($full_name) < 2 || mb_strlen($full_name) > 150) {
        $error = 'กรุณากรอกชื่อ-นามสกุลให้ถูกต้อง (ไม่เกิน 150 ตัวอักษร)';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $error = 'รูปแบบอีเมลไม่ถูกต้อง';
    } elseif (strlen($phone) < 9 || strlen($phone) > 20) {
        $error = 'กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง';
    } elseif (strlen($password) < 8) {
        $error = 'รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร';
    } else {
        $check_sql = 'SELECT id FROM badminton_users WHERE email = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error = 'อีเมลนี้ถูกใช้งานในระบบแล้ว';
            mysqli_stmt_close($stmt);
        } else {
            mysqli_stmt_close($stmt);

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'PLAYER';

            $insert_sql = 'INSERT INTO badminton_users (full_name, email, phone_number, password_hash, role) VALUES (?, ?, ?, ?, ?)';
            if ($stmt = mysqli_prepare($conn, $insert_sql)) {
                mysqli_stmt_bind_param($stmt, 'sssss', $full_name, $email, $phone, $hash, $role);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    header('Location: login.php?registered=1');
                    exit;
                } else {
                    $error = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง';
                    mysqli_stmt_close($stmt);
                }
            } else {
                $error = 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล';
            }
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<main class="wrap">
    <div class="panel">
        <h1>Badminton Court · Register</h1>
        <p>สร้างบัญชีผู้ใช้ใหม่สำหรับระบบจองสนาม</p>

        <?php if ($error): ?><p class="err"><?= auth_escape($error) ?></p><?php endif; ?>

        <form method="post">
            <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
            <label>Full Name</label>
            <input name="full_name" required maxlength="150" value="<?= auth_escape($_POST['full_name'] ?? '') ?>">

            <label>Email</label>
            <input name="email" type="email" required maxlength="190" value="<?= auth_escape($_POST['email'] ?? '') ?>">

            <label>Phone Number</label>
            <input name="phone_number" type="tel" required maxlength="20" value="<?= auth_escape($_POST['phone_number'] ?? '') ?>">

            <label>Password (8+ characters)</label>
            <input name="password" type="password" required minlength="8">

            <p><button>Create Account</button></p>
        </form>
        <p>Already have an account? <a href="login.php">Login</a></p>
    </div>
</main>
</body>
</html>