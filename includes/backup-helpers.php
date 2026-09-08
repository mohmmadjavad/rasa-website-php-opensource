<?php
/**
 * includes/backup-helpers.php
 * منطق مشترک ساخت فایل پشتیبان (SQL + آپلودها در یک ZIP)، استفاده‌شده هم
 * توسط دانلود دستی پشتیبان (admin/api/backup_create.php) و هم توسط
 * زمان‌بند خودکار (resa_run_scheduled_backup_if_due).
 */

if (!defined('RESA_APP')) {
    http_response_code(403);
    exit('Access denied.');
}

const RESA_BACKUP_TABLE_GROUPS = [
    'messages' => ['messages'],
    'projects' => ['projects', 'project_admins', 'project_categories', 'project_members'],
    'articles' => ['articles', 'article_subcategories', 'article_tags', 'categories', 'tags'],
    'comments' => ['comments'],
    'admins'   => ['admins', 'login_attempts'],
    'settings' => ['site_settings'],
    'logs'     => ['activity_logs', 'site_visits'],
];

function resa_backups_dir(): string
{
    $dir = __DIR__ . '/../rasa-manager/storage/backups';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function resa_backup_dump_table(PDO $pdo, string $table): string
{
    $out = "\n-- --------------------------------------------------------\n";
    $out .= "-- جدول: `$table`\n";
    $out .= "-- --------------------------------------------------------\n\n";
    $out .= "DROP TABLE IF EXISTS `$table`;\n";

    $createRow = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
    $createSql = $createRow['Create Table'] ?? '';
    $out .= $createSql . ";\n\n";

    $countStmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
    $total = (int)$countStmt->fetchColumn();
    if ($total === 0) {
        return $out;
    }

    $colsStmt = $pdo->query("SHOW COLUMNS FROM `$table`");
    $columns = array_map(function ($c) { return $c['Field']; }, $colsStmt->fetchAll(PDO::FETCH_ASSOC));
    $colList = '`' . implode('`, `', $columns) . '`';

    $chunk = 500;
    for ($offset = 0; $offset < $total; $offset += $chunk) {
        $rows = $pdo->query("SELECT * FROM `$table` LIMIT $chunk OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) break;
        $valuesSql = [];
        foreach ($rows as $row) {
            $vals = array_map(function ($v) use ($pdo) {
                if ($v === null) return 'NULL';
                return $pdo->quote((string)$v);
            }, array_values($row));
            $valuesSql[] = '(' . implode(', ', $vals) . ')';
        }
        $out .= "INSERT INTO `$table` ($colList) VALUES\n" . implode(",\n", $valuesSql) . ";\n";
    }

    return $out . "\n";
}

function resa_zip_add_dir(ZipArchive $zip, string $srcDir, string $destPrefix): void
{
    if (!is_dir($srcDir)) return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($items as $item) {
        if ($item->isDir()) continue;
        $filePath = $item->getPathname();
        $relativePath = $destPrefix . '/' . substr($filePath, strlen($srcDir) + 1);
        $relativePath = str_replace('\\', '/', $relativePath);
        $zip->addFile($filePath, $relativePath);
    }
}

/**
 * یک فایل ZIP پشتیبان در مسیر موقت می‌سازد و مسیرش را برمی‌گرداند.
 * $scope: 'full' یا 'partial'؛ $sections فقط برای 'partial' استفاده می‌شود.
 * در صورت خطا (مثلاً افزونه Zip نصب نیست)، کلید 'error' پر می‌شود.
 */
function resa_build_backup_zip(PDO $pdo, string $scope, array $sections): array
{
    if (!class_exists('ZipArchive')) {
        return ['error' => 'افزونه Zip روی سرور فعال نیست؛ امکان ساخت فایل پشتیبان وجود ندارد.'];
    }

    $allTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $includeUploads = $scope === 'full' || in_array('uploads', $sections, true);

    if ($scope === 'full') {
        $tables = $allTables;
    } else {
        $tables = [];
        foreach ($sections as $key) {
            if (isset(RESA_BACKUP_TABLE_GROUPS[$key])) {
                $tables = array_merge($tables, RESA_BACKUP_TABLE_GROUPS[$key]);
            }
        }
        $tables = array_values(array_unique(array_intersect($tables, $allTables)));
    }

    if (empty($tables) && !$includeUploads) {
        return ['error' => 'هیچ بخشی برای پشتیبان‌گیری انتخاب نشده است.'];
    }

    $sql = "-- ==========================================================\n";
    $sql .= "-- فایل پشتیبان دیتابیس " . DB_NAME . "\n";
    $sql .= "-- تاریخ ساخت: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- بخش‌ها: " . ($scope === 'full' ? 'کامل (همه جداول' . ($includeUploads ? ' + فایل‌های آپلودی' : '') . ')' : implode(', ', $sections)) . "\n";
    $sql .= "-- ==========================================================\n";
    $sql .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n";

    foreach ($tables as $table) {
        $sql .= resa_backup_dump_table($pdo, $table);
    }
    $sql .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";

    $tmpZipPath = tempnam(sys_get_temp_dir(), 'resa_backup_');
    $zip = new ZipArchive();
    if ($zip->open($tmpZipPath, ZipArchive::OVERWRITE) !== true) {
        return ['error' => 'ساخت فایل پشتیبان با خطا مواجه شد.'];
    }
    $zip->addFromString('database.sql', $sql);
    if ($includeUploads) {
        $uploadsDir = realpath(__DIR__ . '/../assets/uploads');
        if ($uploadsDir) {
            resa_zip_add_dir($zip, $uploadsDir, 'uploads');
        }
    }
    $zip->close();

    return [
        'path'            => $tmpZipPath,
        'tables'          => $tables,
        'include_uploads' => $includeUploads,
    ];
}

/**
 * اگر بک‌آپ خودکار روشن باشد و زمانش رسیده باشد، یک بک‌آپ کامل می‌سازد،
 * در rasa-manager/storage/backups/ ذخیره می‌کند، فقط ۵ فایل آخر را نگه
 * می‌دارد، و زمان آخرین بک‌آپ را در تنظیمات به‌روز می‌کند.
 * چون این پروژه دسترسی Cron واقعی ندارد (مثل هاست‌های اشتراکی/لاراگون)،
 * این تابع در ابتدای هر بارگذاری پنل ادمین «فرصت‌طلبانه» چک می‌شود —
 * هزینه‌اش فقط یک مقایسه‌ی زمان است مگر واقعاً زمان بک‌آپ رسیده باشد.
 */
function resa_run_scheduled_backup_if_due(PDO $pdo): void
{
    $schedule = resa_get_setting($pdo, 'backup_schedule', 'off');
    if ($schedule === 'off') return;

    $intervalSeconds = $schedule === 'weekly' ? 7 * 86400 : 86400;
    $lastAt = resa_get_setting($pdo, 'last_auto_backup_at', '');
    if ($lastAt && (time() - strtotime($lastAt)) < $intervalSeconds) {
        return;
    }

    $result = resa_build_backup_zip($pdo, 'full', []);
    if (!empty($result['error']) || empty($result['path'])) {
        return;
    }

    $dir = resa_backups_dir();
    $filename = 'auto-' . date('Ymd-His') . '.zip';
    $destPath = $dir . '/' . $filename;
    if (@rename($result['path'], $destPath)) {
        @chmod($destPath, 0644);
    } else {
        @copy($result['path'], $destPath);
        @unlink($result['path']);
    }

    resa_set_setting($pdo, 'last_auto_backup_at', date('Y-m-d H:i:s'));

    // فقط ۵ بک‌آپ خودکار آخر را نگه دار
    $files = glob($dir . '/auto-*.zip') ?: [];
    usort($files, function ($a, $b) { return filemtime($b) <=> filemtime($a); });
    foreach (array_slice($files, 5) as $old) {
        @unlink($old);
    }

    resa_log_activity($pdo, null, null, 'backup_auto', 'بک‌آپ خودکار زمان‌بندی‌شده با موفقیت ساخته شد (' . $filename . ').');
}
