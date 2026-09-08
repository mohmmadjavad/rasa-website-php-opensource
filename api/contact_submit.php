<?php
/**
 * api/contact_submit.php
 * دریافت و اعتبارسنجی فرم تماس با ما و ذخیره در دیتابیس.
 */

define('RESA_APP', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');
resa_start_session();

function respond(bool $ok, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respond(false, 'روش درخواست نامعتبر است.');
}

// --- Honeypot: فیلد مخفی که فقط ربات‌ها پر می‌کنند ---
if (!empty($_POST['website'])) {
    respond(false, 'درخواست نامعتبر است.');
}

// --- محدودیت نرخ ارسال: حداقل ۲۰ ثانیه بین دو ارسال از یک نشست ---
if (!empty($_SESSION['last_contact_submit']) && (time() - $_SESSION['last_contact_submit']) < 20) {
    respond(false, 'لطفاً کمی صبر کنید و دوباره تلاش کنید.');
}

// --- محدودیت نرخ بر اساس IP (مستقل از سشن، حداکثر ۵ ارسال در ۱۰ دقیقه) ---
try {
    if (!resa_rate_limit_ok(resa_db(), 'contact_submit', 5, 600)) {
        respond(false, 'تعداد درخواست‌های شما بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.');
    }
} catch (Throwable $e) {
    // اگر خود بررسی نرخ به هر دلیلی خطا داد، اجازه‌ی ادامه بده تا کاربر واقعی مسدود نشود
}

$fullName    = trim($_POST['full_name'] ?? '');
$contactInfo = trim($_POST['contact_info'] ?? '');
$message     = trim($_POST['message'] ?? '');
$captchaInput = trim($_POST['captcha'] ?? '');

$errors = [];

if (mb_strlen($fullName) < 3 || mb_strlen($fullName) > 150) {
    $errors['full_name'] = 'نام و نام خانوادگی را به‌درستی وارد کنید.';
}

$isPhone = (bool)preg_match('/^09[0-9]{9}$/', $contactInfo);
$isEmail = (bool)filter_var($contactInfo, FILTER_VALIDATE_EMAIL);
if (!$isPhone && !$isEmail) {
    $errors['contact_info'] = 'شماره تماس باید ۱۱ رقمی و با ۰۹ شروع شود، یا یک ایمیل معتبر وارد کنید.';
}

if (mb_strlen($message) < 5 || mb_strlen($message) > 3000) {
    $errors['message'] = 'متن پیام باید بین ۵ تا ۳۰۰۰ کاراکتر باشد.';
}

if (empty($_SESSION['contact_captcha']) || empty($_SESSION['contact_captcha_time']) || (time() - $_SESSION['contact_captcha_time']) > 600) {
    $errors['captcha'] = 'کد امنیتی منقضی شده است، دوباره تلاش کنید.';
} elseif ($captchaInput === '' || $captchaInput !== $_SESSION['contact_captcha']) {
    $errors['captcha'] = 'کد امنیتی صحیح نیست.';
}

if (!empty($errors)) {
    // کپچا را برای تلاش بعدی باطل می‌کنیم
    unset($_SESSION['contact_captcha']);
    respond(false, 'لطفاً خطاهای فرم را بررسی کنید.', ['errors' => $errors]);
}

try {
    $pdo = resa_db();
    $stmt = $pdo->prepare("INSERT INTO messages (full_name, contact_info, message, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$fullName, $contactInfo, $message, resa_client_ip()]);

    unset($_SESSION['contact_captcha']);
    $_SESSION['last_contact_submit'] = time();

    respond(true, 'پیام شما با موفقیت ارسال شد. به‌زودی با شما تماس خواهیم گرفت.');
} catch (Throwable $e) {
    respond(false, 'خطایی در ذخیره پیام رخ داد. لطفاً بعداً دوباره تلاش کنید.');
}
