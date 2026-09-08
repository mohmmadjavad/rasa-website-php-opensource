<?php
/**
 * includes/mail-helpers.php
 * توابع سطح بالا برای ساخت/حذف خودکار mailbox هر ادمین روی cPanel،
 * که از api/admins_add.php و api/admins_delete.php صدا زده می‌شوند.
 */

if (!defined('RESA_APP')) {
    http_response_code(403);
    exit('Access denied.');
}

/**
 * از روی نام کاربری ادمین، local-part معتبر برای ایمیل می‌سازد.
 * (نام کاربری‌های سایت می‌توانند نقطه/زیرخط داشته باشند که در ایمیل هم مجازند،
 * ولی برای اطمینان یک بار دیگر پاک‌سازی می‌شود.)
 */
function resa_mail_local_part_from_username(string $username): string
{
    $local = strtolower($username);
    $local = preg_replace('/[^a-z0-9_.\-]/', '', $local) ?? '';
    return $local;
}

/**
 * بعد از ساخت موفق یک ادمین، mailbox متناظرش را روی cPanel می‌سازد و
 * نتیجه را در admin_mailboxes ثبت می‌کند. هیچ‌وقت Exception پرتاب نمی‌کند —
 * شکست در ساخت ایمیل نباید باعث شکست کل عملیات «افزودن ادمین» شود؛
 * در عوض نتیجه (ok/message) برگردانده می‌شود تا در UI به‌صورت هشدار نمایش داده شود.
 *
 * @return array{ok:bool, message:string, email?:string}
 */
function resa_provision_admin_mailbox(PDO $pdo, int $adminId, string $username): array
{
    $localPart = resa_mail_local_part_from_username($username);
    if ($localPart === '') {
        return ['ok' => false, 'message' => 'نام کاربری برای ساخت آدرس ایمیل مناسب نیست.'];
    }

    $email = $localPart . '@' . MAIL_DOMAIN;
    $password = resa_generate_mailbox_password();

    $api = ResaCpanelApi::fromConfig();
    if (!$api->isConfigured()) {
        return ['ok' => false, 'message' => 'اتصال به cPanel تنظیم نشده — بخش CPANEL_* در config.php را پر کنید.'];
    }

    $result = $api->createMailbox($localPart, MAIL_DOMAIN, $password, MAIL_QUOTA_MB);

    $status = $result['ok'] ? 'active' : 'failed';
    $stmt = $pdo->prepare("INSERT INTO admin_mailboxes
        (admin_id, local_part, domain, email_address, password_encrypted, status, last_error, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([
        $adminId,
        $localPart,
        MAIL_DOMAIN,
        $email,
        resa_mail_encrypt($password),
        $status,
        $result['ok'] ? null : $result['message'],
    ]);

    if (!$result['ok']) {
        return ['ok' => false, 'message' => 'ادمین ساخته شد، اما ساخت ایمیل ناموفق بود: ' . $result['message']];
    }

    return ['ok' => true, 'message' => 'ایمیل ' . $email . ' ساخته شد.', 'email' => $email];
}

/**
 * قبل از حذف یک ادمین از دیتابیس صدا زده می‌شود تا mailbox متناظرش هم
 * از روی cPanel پاک شود. ردیف admin_mailboxes خودش با ON DELETE CASCADE
 * وقتی ادمین حذف شود پاک می‌شود، اینجا فقط طرف cPanel را جدا مدیریت می‌کنیم.
 */
function resa_deprovision_admin_mailbox(PDO $pdo, int $adminId): void
{
    $stmt = $pdo->prepare("SELECT local_part, domain FROM admin_mailboxes WHERE admin_id = ? AND status != 'deleted' LIMIT 1");
    $stmt->execute([$adminId]);
    $row = $stmt->fetch();
    if (!$row) {
        return;
    }

    $api = ResaCpanelApi::fromConfig();
    if ($api->isConfigured()) {
        // نتیجه عمداً نادیده گرفته می‌شود: اگر cPanel در دسترس نبود، حذف ادمین
        // نباید متوقف شود؛ mailbox یتیم را می‌شود بعداً دستی هم پاک کرد.
        $api->deleteMailbox($row['local_part'], $row['domain']);
    }
}

/**
 * اطلاعات mailbox یک ادمین را برمی‌گرداند (برای نمایش در پروفایل/تب ایمیل)،
 * پسورد به‌صورت رمزگشایی‌شده فقط وقتی صراحتاً لازم باشد (مثلاً برای SSO به وبمیل).
 */
function resa_get_admin_mailbox(PDO $pdo, int $adminId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM admin_mailboxes WHERE admin_id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$adminId]);
    $row = $stmt->fetch();
    return $row ?: null;
}
