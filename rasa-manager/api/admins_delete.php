<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();
resa_require_manage_admins_api();

$body = api_json_body();
$id = (int)($body['id'] ?? 0);

if ($id <= 0) {
    api_respond(false, null, 'شناسه نامعتبر است.');
}
if ($id === resa_current_admin_id()) {
    api_respond(false, null, 'نمی‌توانید حساب خودتان را حذف کنید.');
}

$countStmt = $pdo->query("SELECT COUNT(*) FROM admins");
if ((int)$countStmt->fetchColumn() <= 1) {
    api_respond(false, null, 'حداقل باید یک ادمین در سیستم باقی بماند.');
}

$targetStmt = $pdo->prepare("SELECT role, avatar FROM admins WHERE id = ?");
$targetStmt->execute([$id]);
$target = $targetStmt->fetch();

if ($target && $target['role'] === 'super_admin') {
    if (!resa_is_super_admin()) {
        api_respond(false, null, 'شما اجازه حذف سوپر ادمین را ندارید.');
    }
    $superCountStmt = $pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'super_admin'");
    if ((int)$superCountStmt->fetchColumn() <= 1) {
        api_respond(false, null, 'حداقل باید یک سوپر ادمین در سیستم باقی بماند.');
    }
}

// قبل از حذف ادمین، mailbox‌اش روی cPanel هم پاک شود (ردیف admin_mailboxes
// خودش با ON DELETE CASCADE به‌محض حذف ادمین از دیتابیس پاک می‌شود)
resa_deprovision_admin_mailbox($pdo, $id);

$stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
$stmt->execute([$id]);

if ($target && !empty($target['avatar'])) {
    resa_delete_uploaded_image($target['avatar']);
}

resa_log_activity(
    $pdo,
    resa_current_admin_id(),
    $_SESSION['admin_username'] ?? null,
    'admin_delete',
    'ادمین با شناسه #' . $id . ' حذف شد.'
);

api_respond(true, null, 'ادمین حذف شد.');
