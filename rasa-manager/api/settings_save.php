<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();
if (!resa_is_super_admin() && !resa_can_manage_site() && !resa_can_manage_backup()) {
    resa_api_forbidden('شما دسترسی این بخش از تنظیمات را ندارید.');
}

$body = api_json_body();

if (array_key_exists('comments_auto_approve', $body) || array_key_exists('maintenance_mode', $body) || array_key_exists('maintenance_message', $body)) {
    if (!resa_is_super_admin() && !resa_can_manage_site()) {
        resa_api_forbidden('شما دسترسی تنظیمات سایت را ندارید.');
    }
}
if (array_key_exists('backup_schedule', $body) && !resa_is_super_admin() && !resa_can_manage_backup()) {
    resa_api_forbidden('شما دسترسی پشتیبان‌گیری را ندارید.');
}

if (array_key_exists('comments_auto_approve', $body)) {
    $on = !empty($body['comments_auto_approve']);
    resa_set_setting($pdo, 'comments_auto_approve', $on ? '1' : '0');
    resa_log_activity(
        $pdo,
        resa_current_admin_id(),
        $_SESSION['admin_username'] ?? null,
        'settings_comments_auto_approve',
        $on ? 'تایید خودکار نظرات کاربران فعال شد.' : 'تایید خودکار نظرات کاربران غیرفعال شد.'
    );
}

if (array_key_exists('maintenance_mode', $body)) {
    $on = !empty($body['maintenance_mode']);
    resa_set_setting($pdo, 'maintenance_mode', $on ? '1' : '0');
    resa_set_maintenance_flag($on);
    resa_log_activity(
        $pdo,
        resa_current_admin_id(),
        $_SESSION['admin_username'] ?? null,
        $on ? 'maintenance_on' : 'maintenance_off',
        $on ? 'حالت تعمیرات سایت فعال شد؛ سایت برای بازدیدکنندگان در دسترس نیست.' : 'حالت تعمیرات سایت غیرفعال شد؛ سایت دوباره در دسترس عموم است.'
    );
}

if (array_key_exists('maintenance_message', $body)) {
    $msg = trim((string)$body['maintenance_message']);
    if (mb_strlen($msg) > 600) {
        $msg = mb_substr($msg, 0, 600);
    }
    resa_set_setting($pdo, 'maintenance_message', $msg);
}

if (array_key_exists('backup_schedule', $body)) {
    $schedule = in_array($body['backup_schedule'], ['off', 'daily', 'weekly'], true) ? $body['backup_schedule'] : 'off';
    resa_set_setting($pdo, 'backup_schedule', $schedule);
    $labels = ['off' => 'خاموش', 'daily' => 'روزانه', 'weekly' => 'هفتگی'];
    resa_log_activity(
        $pdo,
        resa_current_admin_id(),
        $_SESSION['admin_username'] ?? null,
        'settings_backup_schedule',
        'زمان‌بندی پشتیبان‌گیری خودکار روی «' . $labels[$schedule] . '» تنظیم شد.'
    );
}

api_respond(true, null, 'تنظیمات ذخیره شد.');
