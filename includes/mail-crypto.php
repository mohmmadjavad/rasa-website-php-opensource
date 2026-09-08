<?php
/**
 * includes/mail-crypto.php
 * رمزنگاری/رمزگشایی پسورد mailbox قبل از ذخیره در دیتابیس (AES-256-GCM).
 * پسورد mailbox باید قابل رمزگشایی باشد (نه هش) چون برای ورود خودکار
 * به وبمیل (IMAP/SMTP) به آن نیاز داریم — هش یک‌طرفه اینجا کاربردی ندارد.
 */

if (!defined('RESA_APP')) {
    http_response_code(403);
    exit('Access denied.');
}

function resa_mail_encrypt(string $plaintext): string
{
    $key = resa_mail_encryption_key();
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        throw new RuntimeException('خطا در رمزنگاری پسورد ایمیل.');
    }
    return base64_encode($iv . $tag . $cipher);
}

function resa_mail_decrypt(string $encoded): ?string
{
    $key = resa_mail_encryption_key();
    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) < 28) {
        return null;
    }
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? null : $plain;
}

function resa_mail_encryption_key(): string
{
    // MAIL_ENCRYPTION_KEY باید یک رشته‌ی ۳۲ بایتی (یا بیشتر) در config.php باشد؛
    // اینجا با hash به دقیقاً ۳۲ بایت (کلید AES-256) نگاشت می‌شود.
    return hash('sha256', MAIL_ENCRYPTION_KEY, true);
}

/**
 * تولید یک پسورد تصادفی و امن برای mailbox تازه‌ساخته‌شده.
 */
function resa_generate_mailbox_password(int $length = 20): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#%^*';
    $out = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}
