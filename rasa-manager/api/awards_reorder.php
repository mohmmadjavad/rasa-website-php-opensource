<?php
/**
 * rasa-manager/api/awards_reorder.php
 * ثبت ترتیب نمایش جوایز (بخش «دستاوردی چشم‌نواز») بعد از کشیدن و رها کردن در پنل.
 * ورودی: items = [id, id, ...] به ترتیب نمایش دلخواه.
 */
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();
resa_require_super_admin_api();

$body = api_json_body();
$items = $body['items'] ?? [];
if (!is_array($items) || empty($items)) {
    api_respond(false, null, 'داده‌ای برای مرتب‌سازی ارسال نشده است.');
}

$stmt = $pdo->prepare("UPDATE awards SET sort_order = ? WHERE id = ?");

$order = 10;
foreach ($items as $id) {
    $id = (int)$id;
    if ($id <= 0) {
        continue;
    }
    $stmt->execute([$order, $id]);
    $order += 10;
}

api_respond(true, null, 'ترتیب نمایش ذخیره شد.');
