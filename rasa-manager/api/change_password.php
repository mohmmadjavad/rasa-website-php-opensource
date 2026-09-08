<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();

$body = api_json_body();
if (empty($body)) $body = $_POST;

$current = (string)($body['current_password'] ?? '');
$new     = (string)($body['new_password'] ?? '');

if (mb_strlen($new) < 8) {
    api_respond(false, null, 'رمز عبور جدید باید حداقل ۸ کاراکتر باشد.');
}

$stmt = $pdo->prepare("SELECT password_hash FROM admins WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$row = $stmt->fetch();

if (!$row || !password_verify($current, $row['password_hash'])) {
    api_respond(false, null, 'رمز عبور فعلی صحیح نیست.');
}

$hash = password_hash($new, PASSWORD_DEFAULT);
$update = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?");
$update->execute([$hash, $_SESSION['admin_id']]);

api_respond(true, null, 'رمز عبور با موفقیت تغییر کرد.');
