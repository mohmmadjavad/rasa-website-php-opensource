<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();
resa_require_manage_admins_api();

$body = api_json_body();
if (empty($body)) $body = $_POST;

$username = trim((string)($body['username'] ?? ''));
$password = (string)($body['password'] ?? '');

// فقط سوپر ادمین واقعی می‌تواند نقش «سوپر ادمین» بسازد.
// ادمین عادیِ دارای دسترسی «مدیریت ادمین‌ها» فقط می‌تواند ادمین عادی با دسترسی سفارشی بسازد.
$requestedRole = (string)($body['role'] ?? 'admin');
$role = (resa_is_super_admin() && $requestedRole === 'super_admin') ? 'super_admin' : 'admin';

if (mb_strlen($username) < 3 || mb_strlen($username) > 60) {
    api_respond(false, null, 'نام کاربری باید بین ۳ تا ۶۰ کاراکتر باشد.');
}
if (!preg_match('/^[A-Za-z0-9_.\-]+$/', $username)) {
    api_respond(false, null, 'نام کاربری فقط می‌تواند شامل حروف انگلیسی، عدد، خط تیره و زیرخط باشد.');
}
if (mb_strlen($password) < 8) {
    api_respond(false, null, 'رمز عبور باید حداقل ۸ کاراکتر باشد.');
}

$check = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = ?");
$check->execute([$username]);
if ((int)$check->fetchColumn() > 0) {
    api_respond(false, null, 'این نام کاربری قبلاً استفاده شده است.');
}

$permissionsJson = null;
if ($role === 'admin') {
    $permissions = resa_normalize_permissions($body['permissions'] ?? null);
    $permissionsJson = json_encode($permissions, JSON_UNESCAPED_UNICODE);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO admins (username, password_hash, role, permissions, display_name, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
$stmt->execute([$username, $hash, $role, $permissionsJson, $username]);

$newAdminId = (int)$pdo->lastInsertId();

resa_log_activity(
    $pdo,
    resa_current_admin_id(),
    $_SESSION['admin_username'] ?? null,
    'admin_add',
    'ادمین جدید «' . $username . '» با نقش ' . ($role === 'super_admin' ? 'سوپر ادمین' : 'ادمین') . ' اضافه شد.'
);

// ساخت خودکار mailbox روی cPanel — شکست این مرحله باعث شکست کل درخواست نمی‌شود؛
// نتیجه در پاسخ برگردانده می‌شود تا در UI به‌صورت هشدار جدا نمایش داده شود.
$mailResult = resa_provision_admin_mailbox($pdo, $newAdminId, $username);
if ($mailResult['ok']) {
    resa_log_activity($pdo, resa_current_admin_id(), $_SESSION['admin_username'] ?? null, 'admin_mailbox_create', $mailResult['message']);
} else {
    resa_log_activity($pdo, resa_current_admin_id(), $_SESSION['admin_username'] ?? null, 'admin_mailbox_create_failed', $mailResult['message']);
}

api_respond(true, [
    'id'    => $newAdminId,
    'mail'  => $mailResult,
], 'ادمین جدید با موفقیت اضافه شد.');
