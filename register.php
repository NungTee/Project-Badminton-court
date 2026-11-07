<?php
// register.php
date_default_timezone_set('Asia/Bangkok');
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// ดึงไฟล์เชื่อมต่อฐานข้อมูล
if (file_exists(__DIR__ . '/config/db.php')) {
    require_once __DIR__ . '/config/db.php';
} else {
    die("Error: ไม่พบไฟล์ config/db.php กรุณาตรวจสอบโฟลเดอร์");
}

// หากล็อกอินอยู่แล้วให้เด้งไปหน้าหลัก
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phoneNumber = trim($_POST['phone_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($fullName) || empty($email) || empty($password) || empty($confirmPassword)) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'รูปแบบอีเมลไม่ถูกต้อง';
    } elseif ($password !== $confirmPassword) {
        $error = 'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน';
    } elseif (strlen($password) < 6) {
        $error = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
    } else {
        // ตรวจสอบว่าอีเมลซ้ำหรือไม่
        $stmt = mysqli_prepare($conn, "SELECT id FROM badminton_users WHERE email = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $error = 'อีเมลนี้ถูกใช้งานแล้ว กรุณาใช้อีเมลอื่น';
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = 'เกิดข้อผิดพลาดในคำสั่ง SQL: ' . mysqli_error($conn);
        }

        // บันทึกข้อมูลเมื่อไม่มี Error
        if (empty($error)) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'PLAYER';

            $insertStmt = mysqli_prepare($conn, "INSERT INTO badminton_users (full_name, email, phone_number, password_hash, role) VALUES (?, ?, ?, ?, ?)");
            if ($insertStmt) {
                mysqli_stmt_bind_param($insertStmt, "sssss", $fullName, $email, $phoneNumber, $passwordHash, $role);
                if (mysqli_stmt_execute($insertStmt)) {
                    $newUserId = mysqli_insert_id($conn);
                    mysqli_stmt_close($insertStmt);

                    // Auto Login
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $newUserId;
                    $_SESSION['full_name'] = $fullName;
                    $_SESSION['role'] = $role;

                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . mysqli_stmt_error($insertStmt);
                }
            } else {
                $error = 'เกิดข้อผิดพลาดในคำสั่งบันทึก SQL: ' . mysqli_error($conn);
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
    <title>สมัครสมาชิก | Badminton Court Booking</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background-color: #f8fafc;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 30px 15px;
            box-sizing: border-box;
        }
        .register-box {
            background: #ffffff;
            width: 100%;
            max-width: 440px;
            padding: 40px 35px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }
        .register-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 8px 0;
            text-align: center;
        }
        .register-subtitle {
            font-size: 0.9rem;
            color: #64748b;
            margin: 0 0 25px 0;
            text-align: center;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.9rem;
            color: #1e293b;
            margin-bottom: 6px;
        }
        .form-control {
            width: 100%;
            padding: 13px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }
        .form-control:focus {
            border-color: #135c9a;
            box-shadow: 0 0 0 3px rgba(19, 92, 154, 0.15);
        }
        .btn-submit {
            width: 100%;
            padding: 14px;
            background-color: #0f172a;
            color: #ffffff;
            border: none;
            border-radius: 30px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: background-color 0.2s, transform 0.1s;
        }
        .btn-submit:hover {
            background-color: #1e293b;
            transform: translateY(-1px);
        }
        .signin-text {
            text-align: center;
            margin-top: 22px;
            font-size: 0.9rem;
            color: #64748b;
        }
        .signin-text a {
            color: #0f172a;
            font-weight: 600;
            text-decoration: underline;
        }
        .err-msg {
            background-color: #fef2f2;
            color: #dc2626;
            padding: 12px;
            border-radius: 10px;
            font-size: 0.9rem;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #fee2e2;
        }
    </style>
</head>
<body>

<div class="register-box">
    <h2 class="register-title">Create an Account</h2>
    <p class="register-subtitle">เข้าสู่ระบบจองสนามและบริการสั่งซื้ออุปกรณ์</p>

    <?php if ($error): ?>
        <div class="err-msg">⚠️ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" action="register.php">
        <div class="form-group">
            <label for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" class="form-control" placeholder="ชื่อ-นามสกุล" value="<?= htmlspecialchars($_POST['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required autofocus>
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" class="form-control" placeholder="name@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="form-group">
            <label for="phone_number">Phone Number</label>
            <input type="tel" id="phone_number" name="phone_number" class="form-control" placeholder="08X-XXX-XXXX" value="<?= htmlspecialchars($_POST['phone_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" class="form-control" placeholder="อย่างน้อย 6 ตัวอักษร" required>
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="กรอกรหัสผ่านอีกครั้ง" required>
        </div>

        <button type="submit" class="btn-submit">Sign Up</button>

        <div class="signin-text">
            Already have an account? <a href="login.php">Sign in</a>
        </div>
    </form>
</div>

</body>
</html>