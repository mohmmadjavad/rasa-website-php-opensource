<?php
/**
 * rasa-manager/api/dev_team_save.php
 * ذخیره‌ی یکجای انتخاب و ترتیبِ ادمین‌های بخش «افراد توسعه دهنده» (فقط سوپر ادمین).
 * ورودی: items = [{id, enabled, sort_order}, ...]
 */
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();
resa_require_super_admin_api();

$body = api_json_body();
$items = $body['items'] ?? [];
if (!is_array($items)) {
    api_respond(false, null, 'داده نامعتبر است.');
}

$stmt = $pdo->prepare("UPDATE admins SET dev_team_enabled = ?, dev_team_sort_order = ? WHERE id = ?");

foreach ($items as $item) {
    $id = (int)($item['id'] ?? 0);
    if ($id <= 0) {
        continue;
    }
    $enabled = !empty($item['enabled']) ? 1 : 0;
    $sortOrder = (int)($item['sort_order'] ?? 0);
    $stmt->execute([$enabled, $sortOrder, $id]);
}

resa_log_activity($pdo, resa_current_admin_id(), $_SESSION['admin_username'] ?? null, 'dev_team_update', 'به‌روزرسانی بخش افراد توسعه دهنده');
api_respond(true, null, 'تنظیمات ذخیره شد.');
