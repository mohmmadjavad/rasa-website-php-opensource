<?php
/**
 * cron/logs-cleanup-cron.php
 *
 * هر شب یک‌بار توسط Cron Job پنل هاست اجرا می‌شود (نه از مرورگر). دو جدول
 * که فقط اضافه می‌شوند و هیچ‌وقت خودشان پاک نمی‌شدند را هرس می‌کند:
 *
 *   - site_visits    (ردیف خام هر بازدید صفحه): فقط برای نمودار «۷ روز
 *     اخیر» و «امروز» در پنل استفاده می‌شود، پس نگه‌داری بیشتر از یک
 *     هفته لازم نیست. آمار تجمعی/همیشگی («تعداد کل بازدید» و «پربازدیدترین
 *     صفحات») از جدول جداگانه‌ی page_view_totals خوانده می‌شود که این
 *     پاک‌سازی هیچ اثری رویش ندارد.
 *
 *   - activity_logs  (لاگ امنیتی/مدیریتی پنل ادمین: ورود، تغییر تنظیمات،
 *     حذف‌ها و ...): برخلاف site_visits، این یک لاگ امنیتیه، پس بازه‌ی
 *     نگه‌داری آن جداگانه و طولانی‌تر تنظیم شده (پیش‌فرض ۳۰ روز).
 *
 * بازه‌ی نگه‌داری هر جدول در config.php تنظیم می‌شود:
 *   SITE_VISITS_RETENTION_DAYS, ACTIVITY_LOGS_RETENTION_DAYS
 *
 * تنظیم Cron روی cPanel: Cron Jobs → یک ردیف جدید، هر شب ساعت مثلاً ۴ بامداد:
 *   0 4 * * *  /usr/bin/php /home/USERNAME/rasateams.ir/cron/logs-cleanup-cron.php
 * (مسیر PHP و مسیر پروژه را با مقادیر واقعی هاست خودت جایگزین کن)
 */

define('RESA_APP', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('این اسکریپت فقط از طریق Cron Job قابل اجراست.');
}

$siteVisitsRetentionDays = defined('SITE_VISITS_RETENTION_DAYS') ? SITE_VISITS_RETENTION_DAYS : 7;
$activityLogsRetentionDays = defined('ACTIVITY_LOGS_RETENTION_DAYS') ? ACTIVITY_LOGS_RETENTION_DAYS : 30;

$pdo = resa_db();

$delVisits = $pdo->prepare("DELETE FROM site_visits WHERE created_at < (NOW() - INTERVAL ? DAY)");
$delVisits->execute([$siteVisitsRetentionDays]);
$visitsRemoved = $delVisits->rowCount();

$delLogs = $pdo->prepare("DELETE FROM activity_logs WHERE created_at < (NOW() - INTERVAL ? DAY)");
$delLogs->execute([$activityLogsRetentionDays]);
$logsRemoved = $delLogs->rowCount();

echo "site_visits: removed {$visitsRemoved} rows older than {$siteVisitsRetentionDays} days\n";
echo "activity_logs: removed {$logsRemoved} rows older than {$activityLogsRetentionDays} days\n";
