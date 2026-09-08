<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();
resa_require_super_admin_api();

$body = api_json_body();
$page = ($body['page'] ?? '') === 'contact' ? 'contact' : (($body['page'] ?? '') === 'about' ? 'about' : '');
$fields = is_array($body['fields'] ?? null) ? $body['fields'] : [];

if ($page === '') {
    api_respond(false, null, 'صفحه نامعتبر است.');
}

$allowed = [
    'about'   => ['hero_eyebrow', 'hero_title', 'hero_sub', 'story_title', 'story_body'],
    'contact' => ['hero_title', 'hero_sub', 'address', 'phone', 'email', 'hours_days', 'hours_time'],
];

$clean = [];
foreach ($allowed[$page] as $key) {
    if (array_key_exists($key, $fields)) {
        $val = trim((string)$fields[$key]);
        if (mb_strlen($val) > 4000) {
            $val = mb_substr($val, 0, 4000);
        }
        $clean[$key] = $val;
    }
}

$settingKey = $page === 'about' ? 'page_about' : 'page_contact';
$existingRaw = resa_get_setting($pdo, $settingKey, null);
$existing = is_array($decoded = json_decode((string)$existingRaw, true)) ? $decoded : [];
$merged = array_merge($existing, $clean);

resa_set_setting($pdo, $settingKey, json_encode($merged, JSON_UNESCAPED_UNICODE));

resa_log_activity(
    $pdo,
    resa_current_admin_id(),
    $_SESSION['admin_username'] ?? null,
    'page_content_update',
    ($page === 'about' ? 'اطلاعات صفحه درباره ما' : 'اطلاعات صفحه تماس با ما') . ' ویرایش شد.'
);

api_respond(true, $merged, 'اطلاعات صفحه ذخیره شد.');
