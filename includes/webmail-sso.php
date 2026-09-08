<?php
/**
 * includes/webmail-sso.php
 * ساخت و بررسیِ توکن یک‌بارمصرفِ کوتاه‌عمر برای ورود خودکار از پنل ادمین
 * به وبمیل (Roundcube روی mail.MAIL_DOMAIN). دو طرف (پنل اصلی و
 * resa-mail-bridge.php کنار Roundcube) با همین یک کلید مشترک
 * (MAIL_ENCRYPTION_KEY) توکن را امضا/تایید می‌کنند — نیازی به تماس
 * شبکه‌ای بین دو سمت نیست.
 */

if (!defined('RESA_APP')) {
    http_response_code(403);
    exit('Access denied.');
}

/**
 * توکنی می‌سازد که فقط برای همین ادمین و فقط برای چند ثانیه‌ی آینده معتبره.
 * فرمت: base64url(admin_id|expires_at|nonce) + '.' + امضای HMAC-SHA256
 */
function resa_generate_webmail_sso_token(int $adminId): string
{
    $expires = time() + (defined('MAIL_SSO_TOKEN_TTL') ? MAIL_SSO_TOKEN_TTL : 60);
    $nonce = bin2hex(random_bytes(8));
    $payload = $adminId . '|' . $expires . '|' . $nonce;
    $encodedPayload = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $payload, resa_mail_encryption_key());
    return $encodedPayload . '.' . $signature;
}

/**
 * توکن را بررسی می‌کند و در صورت معتبربودن، admin_id را برمی‌گرداند.
 * (این تابع سمت resa-mail-bridge.php هم استفاده می‌شود؛ آنجا باید همین
 * فایل + includes/mail-crypto.php را با همان MAIL_ENCRYPTION_KEY include کند.)
 */
function resa_verify_webmail_sso_token(string $token): ?int
{
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) {
        return null;
    }
    [$encodedPayload, $signature] = $parts;
    $payload = base64_decode(strtr($encodedPayload, '-_', '+/'));
    if ($payload === false) {
        return null;
    }

    $expectedSignature = hash_hmac('sha256', $payload, resa_mail_encryption_key());
    if (!hash_equals($expectedSignature, $signature)) {
        return null;
    }

    $bits = explode('|', $payload);
    if (count($bits) !== 3) {
        return null;
    }
    [$adminId, $expires, $nonce] = $bits;

    if ((int)$expires < time()) {
        return null; // توکن منقضی شده
    }

    return (int)$adminId;
}
