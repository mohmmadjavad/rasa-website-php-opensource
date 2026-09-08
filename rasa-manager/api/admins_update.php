<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();
resa_require_manage_admins_api();

$body = api_json_body();
if (empty($body)) $body = $_POST;

$id = (int)($body['id'] ?? 0);
if ($id <= 0) {
    api_respond(false, null, 'شناسه نامعتبر است.');
}

$targetStmt = $pdo->prepare("SELECT id, role FROM admins WHERE id = ?");
$targetStmt->execute([$id]);
$target = $targetStmt->fetch();
if (!$target) {
    api_respond(false, null, 'ادمین موردنظر یافت نشد.');
}

// ادمین عادی (حتی با دسترسی مدیریت ادمین‌ها) اجازه ندارد سوپر ادمین را ویرایش کند.
if ($target['role'] === 'super_admin' && !resa_is_super_admin()) {
    api_respond(false, null, 'شما اجازه ویرایش سوپر ادمین را ندارید.');
}

$username = isset($body['username']) ? trim((string)$body['username']) : '';
$password = isset($body['password']) ? (string)$body['password'] : '';
$hasPermissionsInput = array_key_exists('permissions', $body) && is_array($body['permissions']);

if ($username === '' && $password === '' && !$hasPermissionsInput) {
    api_respond(false, null, 'حداقل یک فیلد برای بروزرسانی وارد کنید.');
}

$sets = [];
$params = [];

if ($username !== '') {
    if (mb_strlen($username) < 3 || mb_strlen($username) > 60) {
        api_respond(false, null, 'نام کاربری باید بین ۳ تا ۶۰ کاراکتر باشد.');
    }
    if (!preg_match('/^[A-Za-z0-9_.\-]+$/', $username)) {
        api_respond(false, null, 'نام کاربری فقط می‌تواند شامل حروف انگلیسی، عدد، خط تیره و زیرخط باشد.');
    }
    $check = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = ? AND id != ?");
    $check->execute([$username, $id]);
    if ((int)$check->fetchColumn() > 0) {
        api_respond(false, null, 'این نام کاربری قبلاً استفاده شده است.');
    }
    $sets[] = 'username = ?';
    $params[] = $username;
}

if ($password !== '') {
    if (mb_strlen($password) < 8) {
        api_respond(false, null, 'رمز عبور جدید باید حداقل ۸ کاراکتر باشد.');
    }
    $sets[] = 'password_hash = ?';
    $params[] = password_hash($password, PASSWORD_DEFAULT);
}

// دسترسی‌های سفارشی فقط برای ادمین‌های عادی معنا دارد (سوپر ادمین همیشه دسترسی کامل دارد).
if ($hasPermissionsInput && $target['role'] !== 'super_admin') {
    $permissions = resa_normalize_permissions($body['permissions']);
    $sets[] = 'permissions = ?';
    $params[] = json_encode($permissions, JSON_UNESCAPED_UNICODE);
}

if (!$sets) {
    api_respond(false, null, 'تغییری برای ذخیره وجود ندارد.');
}

$params[] = $id;
$stmt = $pdo->prepare('UPDATE admins SET ' . implode(', ', $sets) . ' WHERE id = ?');
$stmt->execute($params);

resa_log_activity(
    $pdo,
    resa_current_admin_id(),
    $_SESSION['admin_username'] ?? null,
    'admin_update',
    'اطلاعات ادمین «' . ($username !== '' ? $username : ('#' . $id)) . '» بروزرسانی شد.'
);

api_respond(true, null, 'اطلاعات ادمین با موفقیت بروزرسانی شد.');
