<?php
// เปิดการแสดง Error สำหรับช่วยค้นหาจุดผิดพลาด (แก้เสร็จแล้วค่อยปิดได้)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config/db.php';

if (!empty($_SESSION['user_id'])) { 
    header('Location: index.php'); 
    exit; 
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_auth_token();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Enter email and password.';
    } else {
        $sql = 'SELECT id, full_name, email, password_hash, role FROM badminton_users WHERE email = ? LIMIT 1';
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            
            $user_id = null;
            $full_name = null;
            $user_email = null;
            $hash = null;
            $role = null;
            
            mysqli_stmt_bind_result($stmt, $user_id, $full_name, $user_email, $hash, $role);
            
            if (mysqli_stmt_fetch($stmt)) {
                mysqli_stmt_close($stmt);
                
                if (password_verify($password, (string)$hash)) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['email'] = $user_email;
                    $_SESSION['role'] = $role;
                    
                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Invalid email or password.';
                }
            } else {
                mysqli_stmt_close($stmt);
                $error = 'Invalid email or password.';
            }
        } else {
            $error = 'Database query failed: ' . mysqli_error($conn);
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<main class="wrap">
    <div class="panel">
        <h1>Badminton Court · Login</h1>
        <p>PHP Session + MySQL Authentication</p>

        <?php if(isset($_GET['registered'])): ?><p class="ok">Registration complete. Please login.</p><?php endif; ?>
        <?php if(isset($_GET['logged_out'])): ?><p class="ok">Logged out.</p><?php endif; ?>
        <?php if($error): ?><p class="err"><?=auth_escape($error)?></p><?php endif; ?>

        <form method="post">
            <input type="hidden" name="auth_csrf" value="<?=auth_escape(auth_token())?>">
            <label>Email</label>
            <input name="email" type="email" required autocomplete="username">
            <label>Password</label>
            <input name="password" type="password" required autocomplete="current-password">
            <p><button>Login</button></p>
        </form>
        <p>New user? <a href="register.php">Register</a></p>
    </div>
</main>
</body>
</html>