<?php
/**
 * rasa-manager/api/team_delete.php
 * حذف عضو تیمی که بدون حساب ادمین اضافه شده بود. کارت ادمین‌ها هرگز از این مسیر حذف نمی‌شود؛
 * برای پنهان‌کردن کارت یک ادمین از تیم، فیلد «نمایش در تیم ما» غیرفعال می‌شود (team_save.php با type=admin).
 */
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();
resa_require_super_admin_api();

$body = api_json_body();
$id = (int)($body['id'] ?? 0);
if ($id <= 0) {
    api_respond(false, null, 'شناسه نامعتبر است.');
}

$stmt = $pdo->prepare("SELECT name, card_image FROM team_extra_members WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    api_respond(false, null, 'عضو تیم یافت نشد.');
}

$del = $pdo->prepare("DELETE FROM team_extra_members WHERE id = ?");
$del->execute([$id]);
if ($row['card_image']) {
    resa_delete_uploaded_image($row['card_image']);
}

resa_log_activity($pdo, resa_current_admin_id(), $_SESSION['admin_username'] ?? null, 'team_extra_delete', 'حذف عضو تیم: ' . $row['name']);
api_respond(true, null, 'عضو تیم حذف شد.');
