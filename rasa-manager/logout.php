<?php
define('RESA_APP', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

resa_start_session();

if (!empty($_SESSION['admin_id'])) {
    try {
        $pdo = resa_db();
        resa_log_activity($pdo, (int)$_SESSION['admin_id'], $_SESSION['admin_username'] ?? null, 'logout', 'خروج از پنل ادمین.');
    } catch (Throwable $e) {
        // خطای احتمالی ثبت لاگ نباید مانع خروج کاربر شود
    }
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: login.php');
exit;
