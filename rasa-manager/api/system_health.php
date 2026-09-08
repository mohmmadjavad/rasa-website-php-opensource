<?php
/**
 * admin/api/system_health.php
 * وضعیت لحظه‌ای سیستم برای بخش «سلامت سیستم»: اتصال و حجم دیتابیس، فضای
 * آزاد دیسک، نسخه‌ی PHP، تعداد ردیف‌های جدول‌های اصلی، و فهرست بک‌آپ‌های
 * موجود روی سرور (دستی و خودکار). فقط سوپر ادمین.
 */
require_once __DIR__ . '/_bootstrap.php';
resa_require_view_health_api();

$out = [];

/* ---------- دیتابیس ---------- */
try {
    $pdo->query('SELECT 1');
    $out['db_connected'] = true;
} catch (Throwable $e) {
    $out['db_connected'] = false;
}

$sizeRow = $pdo->query("
    SELECT ROUND(SUM(data_length + index_length), 0) AS size_bytes, COUNT(*) AS table_count
    FROM information_schema.TABLES WHERE table_schema = DATABASE()
")->fetch();
$out['db_size_bytes'] = (int)($sizeRow['size_bytes'] ?? 0);
$out['db_table_count'] = (int)($sizeRow['table_count'] ?? 0);

$out['counts'] = [
    'articles' => (int)$pdo->query("SELECT COUNT(*) FROM articles")->fetchColumn(),
    'projects' => (int)$pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn(),
    'comments' => (int)$pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn(),
    'messages' => (int)$pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn(),
    'admins'   => (int)$pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn(),
];

/* ---------- سرور ---------- */
$root = realpath(__DIR__ . '/../..');
$freeBytes = $root ? @disk_free_space($root) : false;
$totalBytes = $root ? @disk_total_space($root) : false;
$out['disk_free_bytes'] = $freeBytes !== false ? (int)$freeBytes : null;
$out['disk_total_bytes'] = $totalBytes !== false ? (int)$totalBytes : null;
$out['php_version'] = PHP_VERSION;

/* ---------- پشتیبان‌ها ---------- */
$dir = resa_backups_dir();
$files = glob($dir . '/*.zip') ?: [];
usort($files, function ($a, $b) { return filemtime($b) <=> filemtime($a); });

$backups = [];
foreach (array_slice($files, 0, 5) as $f) {
    $backups[] = [
        'filename'  => basename($f),
        'size_bytes' => filesize($f),
        'created_at' => date('Y-m-d H:i:s', filemtime($f)),
        'is_auto'   => strpos(basename($f), 'auto-') === 0,
    ];
}
$out['backups'] = $backups;
$out['last_backup_at'] = $backups[0]['created_at'] ?? null;
$out['backup_schedule'] = resa_get_setting($pdo, 'backup_schedule', 'off');

api_respond(true, $out);
