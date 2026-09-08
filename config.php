<?php
/**
 * config.php
 * تنظیمات اصلی سایت — این فایل را قبل از آپلود روی هاست ویرایش کن.
 * این فایل نباید مستقیماً در مرورگر باز شود (توسط .htaccess بلاک شده است).
 */

if (!defined('RESA_APP')) {
    http_response_code(403);
    exit('Access denied.');
}

/* ---------- امنیت نمایش خطاها ---------- */
// در حالت تولید (روی هاست واقعی)، نمایش خطاهای PHP به کاربر باید خاموش باشد
// تا مسیر فایل‌ها یا جزئیات داخلی سرور (که می‌تواند به مهاجم در حمله کمک کند)
// فاش نشود. خطاها همچنان در لاگ سرور ثبت می‌شوند تا خودتان بتوانید بررسی‌شان کنید.
//
// این تشخیص به‌صورت خودکار انجام می‌شود: فقط وقتی روی localhost/Laragon در
// حال توسعه هستید خطاها روی صفحه نمایش داده می‌شوند؛ به محض اینکه سایت را
// روی یک دامنه‌ی واقعی (هاست) بالا بیاورید، این کد خودش نمایش خطاها را
// خاموش می‌کند — حتی اگر یادتان برود این تنظیم را قبل از انتشار عوض کنید.
$resaHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '')));
$resaIsLocalDev = ($resaHost === '')
    || in_array($resaHost, ['localhost', '127.0.0.1', '::1'], true)
    || str_ends_with($resaHost, '.local')
    || str_ends_with($resaHost, '.test')
    || php_sapi_name() === 'cli';

if ($resaIsLocalDev) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}
error_reporting(E_ALL);
ini_set('log_errors', '1');

/* ---------- اطلاعات اتصال به دیتابیس MySQL ---------- */
/* این مقادیر را از پنل هاست (cPanel > MySQL Databases) بردار و اینجا جایگزین کن */
define('DB_HOST', 'localhost');
define('DB_NAME', 'resa_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/* ---------- تنظیمات امنیتی ---------- */
// یک رشته‌ی طولانی و تصادفی برای امن‌سازی نشست‌ها. قبل از انتشار عوضش کن.
define('APP_SECRET', 'change-this-to-a-long-random-string-1234567890');

// حداکثر تلاش ناموفق ورود قبل از قفل موقت
define('LOGIN_MAX_ATTEMPTS', 5);
// مدت قفل شدن پس از عبور از حداکثر تلاش (ثانیه)
define('LOGIN_LOCK_SECONDS', 900); // 15 دقیقه

/* ---------- منطقه زمانی ---------- */
date_default_timezone_set('Asia/Tehran');

/* ---------- اتصال به cPanel (برای ساخت خودکار ایمیل ادمین‌ها) ---------- */
// این توکن را از داخل خودِ cPanel بساز: Security > Manage API Tokens > Create
// (نیازی به دسترسی WHM/ریسلر نیست، روی هاست اشتراکی معمولی هم کار می‌کند)
define('CPANEL_HOST', 'yourdomain.ir');      // معمولاً همون دامنه یا آدرس سرور هاست، بدون https://
define('CPANEL_PORT', 2083);
define('CPANEL_USERNAME', '');               // یوزرنیم اکانت cPanel (نه یوزرنیم ادمین سایت)
define('CPANEL_API_TOKEN', '');              // توکنی که از cPanel ساختی

/* ---------- بازه‌ی نگه‌داری لاگ‌ها (کرون cron/logs-cleanup-cron.php) ----------
   site_visits فقط برای نمودار «۷ روز اخیر» پنل استفاده می‌شود (آمار همیشگی
   از جدول جدا page_view_totals می‌آید که این پاک‌سازی رویش اثر ندارد).
   activity_logs یک لاگ امنیتی/مدیریتی است، پس بازه‌ی جداگانه و طولانی‌تری دارد. */
define('SITE_VISITS_RETENTION_DAYS', 7);
define('ACTIVITY_LOGS_RETENTION_DAYS', 30);

/* ---------- تنظیمات ایمیل ادمین‌ها ---------- */
define('MAIL_DOMAIN', 'rasateams.ir');       // ایمیل هر ادمین به‌صورت username@MAIL_DOMAIN ساخته می‌شود
define('MAIL_QUOTA_MB', 300);                // حجم صندوق هر ادمین (مگابایت) — سقف امنیتی؛ کرون ۳۰ روزه معمولاً قبل از رسیدن به این عدد پیوست‌های قدیمی رو پاک می‌کنه
define('MAIL_ATTACHMENT_MAX_MB', 10);        // حداکثر حجم مجاز هر پیوست
define('MAIL_ATTACHMENT_RETENTION_DAYS', 30);// بعد از این تعداد روز، پیوست‌ها حذف می‌شوند (متن ایمیل می‌ماند)

// آدرس نصب Roundcube (فاز ۲) — بعد از نصب روی ساب‌دامین mail، این را عوض کن
define('MAIL_WEBMAIL_URL', 'https://mail.rasateams.ir/resa-mail-bridge.php');
define('MAIL_SSO_TOKEN_TTL', 60); // ثانیه — توکن ورود خودکار فقط همین‌قدر معتبره

/* ---------- اتصال IMAP برای اسکریپت کرون پاکسازی پیوست‌ها (فاز ۴) ---------- */
// معمولاً روی هاست اشتراکی، سرور IMAP همون هاستیه که سایت رویشه؛
// یا localhost بذار یا mail.MAIL_DOMAIN — با پشتیبانی هاست چک کن کدوم درست کار می‌کنه
define('MAIL_IMAP_HOST', 'localhost');
define('MAIL_IMAP_PORT', 993);

// کلید رمزنگاری پسورد mailbox‌ها قبل از ذخیره در دیتابیس — قبل از انتشار حتماً عوضش کن
// (یک رشته‌ی بلند و تصادفی؛ جدا از APP_SECRET نگه‌دار)
define('MAIL_ENCRYPTION_KEY', 'change-this-to-a-second-long-random-string-0987654321');