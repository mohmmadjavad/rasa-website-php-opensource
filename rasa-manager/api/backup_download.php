<?php
/**
 * admin/api/backup_download.php
 * دانلود یکی از فایل‌های پشتیبانِ از قبل ساخته‌شده در rasa-manager/storage/backups
 * (دستی یا خودکار). فقط سوپر ادمین. نام فایل با basename() پاک‌سازی می‌شود تا
 * امکان خروج از پوشه (Path Traversal) وجود نداشته باشد.
 */
require_once __DIR__ . '/_bootstrap.php';
resa_require_manage_backup_api();

$filename = basename((string)($_GET['file'] ?? ''));
if ($filename === '' || !preg_match('/^[A-Za-z0-9_\-\.]+\.zip$/', $filename)) {
    http_response_code(400);
    exit('نام فایل نامعتبر است.');
}

$path = resa_backups_dir() . '/' . $filename;
if (!is_file($path)) {
    http_response_code(404);
    exit('فایل پشتیبان یافت نشد.');
}

resa_log_activity(
    $pdo,
    resa_current_admin_id(),
    $_SESSION['admin_username'] ?? null,
    'backup_download',
    'دانلود فایل پشتیبان ذخیره‌شده روی سرور (' . $filename . ').'
);

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
