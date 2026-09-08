<?php
/**
 * admin/api/backup_create.php
 * ساخت فایل پشتیبان ZIP شامل دامپ SQL دیتابیس و (در صورت انتخاب) فایل‌های آپلودی،
 * با استفاده از منطق مشترک includes/backup-helpers.php. یک نسخه هم در
 * rasa-manager/storage/backups/ ذخیره می‌شود تا در بخش «سلامت سیستم» قابل مشاهده باشد.
 * فقط سوپر ادمین به این بخش دسترسی دارد.
 */
require_once __DIR__ . '/_bootstrap.php';
resa_require_manage_backup_api();
api_require_csrf();

set_time_limit(0);

$body = api_json_body();
$scope = ($body['scope'] ?? 'full') === 'partial' ? 'partial' : 'full';
$sections = is_array($body['sections'] ?? null) ? $body['sections'] : [];

$result = resa_build_backup_zip($pdo, $scope, $sections);
if (!empty($result['error'])) {
    api_respond(false, null, $result['error']);
}

$tmpZipPath = $result['path'];
$filename = 'resa-backup-' . ($scope === 'full' ? 'full' : implode('-', $sections)) . '-' . date('Ymd-His') . '.zip';

// یک نسخه‌ی پایدار هم در storage/backups نگه می‌داریم تا «آخرین بک‌آپ» در
// بخش سلامت سیستم درست نمایش داده شود (حتی برای بک‌آپ‌های دستی).
$persistPath = resa_backups_dir() . '/' . $filename;
@copy($tmpZipPath, $persistPath);
resa_set_setting($pdo, 'last_auto_backup_at', date('Y-m-d H:i:s'));

resa_log_activity(
    $pdo,
    resa_current_admin_id(),
    $_SESSION['admin_username'] ?? null,
    'backup_download',
    'دانلود فایل پشتیبان (' . ($scope === 'full' ? 'کامل' : implode(', ', $sections)) . ($result['include_uploads'] ? ' + فایل‌ها' : '') . ')'
);

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpZipPath));
readfile($tmpZipPath);
unlink($tmpZipPath);
exit;
