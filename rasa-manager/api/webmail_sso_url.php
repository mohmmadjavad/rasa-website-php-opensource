<?php
require_once __DIR__ . '/_bootstrap.php';

$mailbox = resa_get_admin_mailbox($pdo, resa_current_admin_id());
if (!$mailbox) {
    api_respond(false, null, 'صندوق ایمیلی برای حساب شما پیدا نشد.');
}

$token = resa_generate_webmail_sso_token(resa_current_admin_id());
$url = MAIL_WEBMAIL_URL . '?token=' . urlencode($token);

api_respond(true, ['url' => $url, 'email' => $mailbox['email_address']]);
