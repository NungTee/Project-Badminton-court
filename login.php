<?php
// login.php
date_default_timezone_set('Asia/Bangkok');
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (file_exists(__DIR__ . '/config/db.php')) {
    require_once __DIR__ . '/config/db.php';
} else {
    die("Error: ไม่พบไฟล์ config/db.php");
}

// หากล็อกอินอยู่แล้วให้เด้งไปหน้าหลัก
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'กรุณากรอกอีเมลและรหัสผ่าน';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id, full_name, password_hash, role FROM badminton_users WHERE email = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                
                header('Location: index.php');
                exit;
            } else {
                $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
            }
        } else {
            $error = 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล';
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เข้าสู่ระบบ | BADMINTON COURT</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background-color: #f8fafc;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px 15px;
            box-sizing: border-box;
        }
        .app-title {
            font-size: 2rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: 1px;
            margin-bottom: 25px;
            text-align: center;
            text-transform: uppercase;
        }
        .login-box {
            background: #ffffff;
            width: 100%;
            max-width: 420px;
            padding: 40px 35px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }
        .form-group {
            margin-bottom: 22px;
        }
        .form-label-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .form-group label {
            font-weight: 600;
            font-size: 0.95rem;
            color: #1e293b;
        }
        .forgot-link {
            font-size: 0.88rem;
            color: #64748b;
            text-decoration: underline;
        }
        .forgot-link:hover {
            color: #0f172a;
        }
        .form-control {
            width: 100%;
            padding: 14px 16px;
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
        .signup-text {
            text-align: center;
            margin-top: 25px;
            font-size: 0.9rem;
            color: #64748b;
        }
        .signup-text a {
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

<!-- หัวข้อระบบด้านบนการ์ดล็อกอิน -->
<h1 class="app-title">🏸 BADMINTON COURT</h1>

<div class="login-box">
    <?php if ($error): ?>
        <div class="err-msg">⚠️ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" action="login.php">
        <div class="form-group">
            <label for="email">Username or Email</label>
            <input type="text" id="email" name="email" class="form-control" placeholder="" required autofocus>
        </div>

        <div class="form-group">
            <div class="form-label-row">
                <label for="password">Password</label>
                <a href="#" class="forgot-link" onclick="alert('กรุณาติดต่อเจ้าหน้าที่เพื่อรีเซ็ตรหัสผ่าน'); return false;">Forgot?</a>
            </div>
            <input type="password" id="password" name="password" class="form-control" placeholder="" required>
        </div>

        <button type="submit" class="btn-submit">Sign In</button>

        <div class="signup-text">
            Don't have an account? <a href="register.php">Sign up</a>
        </div>
    </form>
</div>

</body>
</html>