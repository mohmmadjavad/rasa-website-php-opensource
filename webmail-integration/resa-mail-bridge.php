<?php
/**
 * resa-mail-bridge.php
 * ================================================================
 * این فایل را بعد از نصب Roundcube، داخل همون پوشه‌ی ریشه‌ی Roundcube
 * (کنار index.php خود Roundcube) آپلود کن. تب «ایمیل» پنل ادمین با یک
 * توکن امضاشده به همین آدرس (MAIL_WEBMAIL_URL در config.php اصلی) وصل
 * می‌شود؛ این اسکریپت توکن را بررسی می‌کند، با پسورد mailbox واقعی وارد
 * Roundcube می‌شود و کاربر را به صفحه‌ی اصلی وبمیل هدایت می‌کند.
 *
 * چرا این‌طوری؟ چون این فایل و خودِ Roundcube روی یک دامنه (mail.*) هستند،
 * کوکی نشستی که اینجا از Roundcube می‌گیریم به‌درستی روی همون دامنه ست
 * می‌شود و مرورگر بعدش با همون کوکی وارد Roundcube می‌شود — بدون نیاز به
 * وارد کردن دستی پسورد.
 * ================================================================
 */

// ---------- تنظیم مسیر پروژه‌ی اصلی (سایت رسا) روی هاست ----------
// چون این اسکریپت روی ساب‌دامین mail.rasateams.ir اجرا می‌شود ولی به فایل‌های
// پروژه‌ی اصلی (config.php و includes/) نیاز دارد، مسیر واقعی را اینجا بگذار.
// روی cPanel معمولاً چیزی شبیه این است (با یوزرنیم واقعی هاستت جایگزین کن):
//   /home/USERNAME/public_html/config.php               (اگر سایت اصلی روی دامنه‌ی اصلی است)
//   /home/USERNAME/rasateams.ir/config.php               (اگر addon domain جداست)
define('RESA_MAIN_APP_PATH', '/home/USERNAME/rasateams.ir');

define('RESA_APP', true);
require_once RESA_MAIN_APP_PATH . '/config.php';
require_once RESA_MAIN_APP_PATH . '/includes/db.php';
require_once RESA_MAIN_APP_PATH . '/includes/mail-crypto.php';
require_once RESA_MAIN_APP_PATH . '/includes/webmail-sso.php';

function resa_bridge_fail(string $message): void
{
    http_response_code(403);
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
        . '<title>خطا در ورود به ایمیل</title>'
        . '<style>body{font-family:Tahoma,sans-serif;background:#10181a;color:#f4fafb;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}'
        . '.box{text-align:center;padding:2rem;max-width:420px}</style></head>'
        . '<body><div class="box"><h2>ورود به ایمیل ناموفق بود</h2><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p>به تب ایمیل پنل ادمین برگرد و دوباره تلاش کن.</p></div></body></html>';
    exit;
}

$token = $_GET['token'] ?? '';
if ($token === '') {
    resa_bridge_fail('توکن ورود پیدا نشد.');
}

$adminId = resa_verify_webmail_sso_token($token);
if ($adminId === null) {
    resa_bridge_fail('توکن نامعتبر یا منقضی شده است.');
}

$pdo = resa_db();
$stmt = $pdo->prepare("SELECT email_address, password_encrypted FROM admin_mailboxes WHERE admin_id = ? AND status = 'active' LIMIT 1");
$stmt->execute([$adminId]);
$mailbox = $stmt->fetch();
if (!$mailbox) {
    resa_bridge_fail('صندوق ایمیلی برای این حساب پیدا نشد.');
}

$password = resa_mail_decrypt($mailbox['password_encrypted']);
if ($password === null) {
    resa_bridge_fail('خطا در بازیابی اطلاعات ورود ایمیل.');
}

// ---------- مرحله ۱: گرفتن توکن CSRF فرم لاگین Roundcube ----------
$baseUrl = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/';
$cookieJar = tempnam(sys_get_temp_dir(), 'resa_rc_');

$ch = curl_init($baseUrl . '?_task=login');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR      => $cookieJar,
    CURLOPT_COOKIEFILE     => $cookieJar,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$loginPageHtml = curl_exec($ch);
curl_close($ch);

if ($loginPageHtml === false || !preg_match('/name="_token"\s+value="([^"]+)"/', $loginPageHtml, $m)) {
    @unlink($cookieJar);
    resa_bridge_fail('اتصال به سرور وبمیل ناموفق بود (توکن CSRF پیدا نشد).');
}
$requestToken = $m[1];

// ---------- مرحله ۲: ارسال فرم لاگین واقعی با یوزر/پسورد mailbox، و forward کردن کوکی نشست به مرورگر ----------
// نکته‌ی مهم: باید همون کوکی‌جارِ مرحله‌ی ۱ اینجا هم استفاده شود، چون توکن
// CSRF به نشست همون درخواست اول وصل است.
resa_bridge_forward_login($baseUrl, $mailbox['email_address'], $password, $requestToken, $cookieJar);

function resa_bridge_forward_login(string $baseUrl, string $user, string $pass, string $token, string $cookieJar): void
{
    $ch = curl_init($baseUrl . '?_task=login&_action=login');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            '_token'    => $token,
            '_task'     => 'login',
            '_action'   => 'login',
            '_timezone' => 'Asia/Tehran',
            '_url'      => '',
            '_user'     => $user,
            '_pass'     => $pass,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_COOKIEJAR      => $cookieJar,
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    @unlink($cookieJar);

    $headers = substr($response, 0, $headerSize);
    preg_match_all('/^Set-Cookie:\s*(.+)$/mi', $headers, $matches);
    foreach ($matches[1] as $cookieLine) {
        header('Set-Cookie: ' . $cookieLine, false);
    }

    header('Location: ' . $baseUrl);
    exit;
}
