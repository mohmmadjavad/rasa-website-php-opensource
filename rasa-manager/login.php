<?php
/**
 * admin/login.php
 * صفحه ورود به پنل ادمین.
 */

define('RESA_APP', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

resa_start_session();
$pdo = resa_db();

if (resa_is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!resa_csrf_check($_POST['csrf'] ?? '')) {
        $error = 'نشست شما منقضی شده، صفحه را رفرش کنید و دوباره تلاش کنید.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $ip = resa_client_ip();
        $lockKey = $ip . '|' . mb_strtolower($username);

        if ($username === '' || $password === '') {
            $error = 'نام کاربری و رمز عبور را وارد کنید.';
        } elseif (resa_login_is_locked($pdo, $lockKey) || resa_login_is_locked($pdo, $ip)) {
            $error = 'به دلیل تلاش‌های ناموفق زیاد، ورود موقتاً قفل شده است. کمی بعد دوباره تلاش کنید.';
        } else {
            $stmt = $pdo->prepare("SELECT id, username, password_hash, role, permissions, display_name, avatar FROM admins WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                resa_login_clear_attempts($pdo, $lockKey);
                resa_login_clear_attempts($pdo, $ip);
                session_regenerate_id(true);
                $_SESSION['admin_id']       = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_role']     = $admin['role'];
                $_SESSION['admin_permissions'] = resa_normalize_permissions($admin['permissions']);
                $_SESSION['admin_display_name'] = $admin['display_name'] ?: $admin['username'];
                $_SESSION['admin_avatar']   = $admin['avatar'];
                $_SESSION['last_regen']     = time();
                resa_log_activity($pdo, (int)$admin['id'], $admin['username'], 'login_success', 'ورود موفق به پنل ادمین.');
                header('Location: index.php');
                exit;
            } else {
                resa_login_record_attempt($pdo, $lockKey);
                resa_login_record_attempt($pdo, $ip);
                resa_log_activity($pdo, null, $username !== '' ? $username : null, 'login_failed', 'تلاش ناموفق برای ورود به پنل ادمین.');
                $error = 'نام کاربری یا رمز عبور اشتباه است.';
            }
        }
    }
}

$csrf = resa_csrf_token();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ورود به پنل ادمین | رسا</title>
<link rel="icon" href="../assets/logo/favicon.svg" type="image/svg+xml">
<link rel="icon" href="../assets/logo/favicon-96x96.png" type="image/png" sizes="96x96">
<link rel="shortcut icon" href="../assets/logo/favicon.ico">
<link rel="apple-touch-icon" sizes="180x180" href="../assets/logo/apple-touch-icon-admin.png">
<link rel="manifest" href="manifest.webmanifest">
<meta name="theme-color" content="#171a1a">
<link href="../assets/vendor/vazirmatn/Vazirmatn-font-face.css" rel="stylesheet">
<link rel="stylesheet" href="assets/admin.css">
</head>
<body class="login-body">
  <div class="login-wrap">
    <div class="login-card">
      <img class="login-logo" src="../assets/logo/logo-filled-cyan.svg" alt="رسا">
      <h1>ورود به پنل ادمین</h1>
      <p class="login-sub">رسا</p>

      <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <label>نام کاربری</label>
        <input type="text" name="username" required autofocus>
        <label>رمز عبور</label>
        <input type="password" name="password" required>
        <button type="submit">ورود</button>
      </form>
    </div>
  </div>
</body>
</html>
