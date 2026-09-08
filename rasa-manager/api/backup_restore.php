<?php
/**
 * admin/api/backup_restore.php
 * بازیابی دیتابیس (و در صورت وجود، فایل‌های آپلودی) از یک فایل پشتیبان ZIP یا SQL خام.
 * فقط سوپر ادمین به این بخش دسترسی دارد.
 */
require_once __DIR__ . '/_bootstrap.php';
resa_require_manage_backup_api();
api_require_csrf();

set_time_limit(0);

if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
    api_respond(false, null, 'فایل پشتیبان دریافت نشد یا در آپلود آن خطایی رخ داد.');
}

$tmpPath = $_FILES['backup_file']['tmp_name'];
$originalName = $_FILES['backup_file']['name'];
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if (!in_array($ext, ['sql', 'zip'], true)) {
    api_respond(false, null, 'فقط فایل‌های SQL یا ZIP قابل بازیابی هستند.');
}

$sqlContent = '';
$restoredFiles = 0;

if ($ext === 'zip') {
    if (!class_exists('ZipArchive')) {
        api_respond(false, null, 'افزونه Zip روی سرور فعال نیست؛ امکان بازیابی از فایل ZIP وجود ندارد.');
    }
    $zip = new ZipArchive();
    if ($zip->open($tmpPath) !== true) {
        api_respond(false, null, 'باز کردن فایل ZIP با خطا مواجه شد.');
    }

    $sqlEntryName = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if ($name === 'database.sql' || (!$sqlEntryName && substr($name, -4) === '.sql')) {
            $sqlEntryName = $name;
        }
    }
    if (!$sqlEntryName) {
        $zip->close();
        api_respond(false, null, 'فایل database.sql داخل فایل ZIP پیدا نشد.');
    }
    $sqlContent = (string)$zip->getFromName($sqlEntryName);

    $uploadsBase = realpath(__DIR__ . '/../../assets/uploads');
    if ($uploadsBase) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (strpos($name, 'uploads/') === 0 && substr($name, -1) !== '/') {
                $relative = substr($name, strlen('uploads/'));
                if ($relative === '' || strpos($relative, '..') !== false) continue;
                $destPath = $uploadsBase . '/' . $relative;
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) {
                    @mkdir($destDir, 0775, true);
                }
                $content = $zip->getFromName($name);
                if ($content !== false && file_put_contents($destPath, $content) !== false) {
                    $restoredFiles++;
                }
            }
        }
    }
    $zip->close();
} else {
    $sqlContent = (string)file_get_contents($tmpPath);
}

if (trim($sqlContent) === '') {
    api_respond(false, null, 'محتوای SQL فایل پشتیبان خالی است.');
}

function resa_split_sql_statements(string $sql): array
{
    $statements = [];
    $current = '';
    $len = strlen($sql);
    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $current .= $ch;

        if ($ch === '\\' && ($inSingle || $inDouble)) {
            if ($i + 1 < $len) {
                $i++;
                $current .= $sql[$i];
            }
            continue;
        }
        if ($ch === "'" && !$inDouble && !$inBacktick) {
            $inSingle = !$inSingle;
            continue;
        }
        if ($ch === '"' && !$inSingle && !$inBacktick) {
            $inDouble = !$inDouble;
            continue;
        }
        if ($ch === '`' && !$inSingle && !$inDouble) {
            $inBacktick = !$inBacktick;
            continue;
        }
        if ($ch === ';' && !$inSingle && !$inDouble && !$inBacktick) {
            $statements[] = trim(substr($current, 0, -1));
            $current = '';
        }
    }
    if (trim($current) !== '') {
        $statements[] = trim($current);
    }

    $result = [];
    foreach ($statements as $stmt) {
        $meaningful = false;
        foreach (explode("\n", $stmt) as $line) {
            $t = trim($line);
            if ($t === '' || strpos($t, '--') === 0) continue;
            $meaningful = true;
            break;
        }
        if ($meaningful) $result[] = $stmt;
    }
    return $result;
}

$statements = resa_split_sql_statements($sqlContent);

$ok = 0;
$failed = 0;
$errors = [];

foreach ($statements as $stmt) {
    try {
        $pdo->exec($stmt);
        $ok++;
    } catch (Throwable $e) {
        $failed++;
        if (count($errors) < 8) {
            $errors[] = mb_substr($e->getMessage(), 0, 200);
        }
    }
}

resa_log_activity(
    $pdo,
    resa_current_admin_id(),
    $_SESSION['admin_username'] ?? null,
    'backup_restore',
    "بازیابی از فایل پشتیبان — دستورات موفق: $ok، ناموفق: $failed" . ($restoredFiles ? "، فایل‌های بازیابی‌شده: $restoredFiles" : '')
);

api_respond(true, [
    'executed'       => $ok,
    'failed'         => $failed,
    'restored_files' => $restoredFiles,
    'errors'         => $errors,
], 'بازیابی انجام شد. دستورات موفق: ' . $ok . ($failed ? ' — دستورات ناموفق: ' . $failed : '') . ($restoredFiles ? ' — فایل بازیابی‌شده: ' . $restoredFiles : '') . '.');
