<?php
/**
 * rasa-manager/api/team_reorder.php
 * ثبت ترتیب یکپارچه‌ی نمایش کارت‌های تیم (ادمین‌ها + اعضای بدون حساب) بعد از درگ‌اند‌دراپ در پنل.
 * ورودی: items = [{type:'admin'|'extra', id:N}, ...] به ترتیب نمایش دلخواه.
 */
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();
resa_require_super_admin_api();

$body = api_json_body();
$items = $body['items'] ?? [];
if (!is_array($items) || empty($items)) {
    api_respond(false, null, 'داده‌ای برای مرتب‌سازی ارسال نشده است.');
}

$stmtAdmin = $pdo->prepare("UPDATE admins SET team_card_sort_order = ? WHERE id = ?");
$stmtExtra = $pdo->prepare("UPDATE team_extra_members SET sort_order = ? WHERE id = ?");

$order = 10;
foreach ($items as $item) {
    $type = ($item['type'] ?? '') === 'admin' ? 'admin' : (($item['type'] ?? '') === 'extra' ? 'extra' : null);
    $id = (int)($item['id'] ?? 0);
    if (!$type || $id <= 0) {
        continue;
    }
    if ($type === 'admin') {
        $stmtAdmin->execute([$order, $id]);
    } else {
        $stmtExtra->execute([$order, $id]);
    }
    $order += 10;
}

api_respond(true, null, 'ترتیب نمایش ذخیره شد.');
