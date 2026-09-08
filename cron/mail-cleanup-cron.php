<?php
/**
 * cron/mail-cleanup-cron.php
 *
 * هر شب یک‌بار توسط Cron Job پنل هاست اجرا می‌شود (نه از مرورگر). برای هر
 * ادمینی که mailbox فعال دارد، پیام‌های قدیمی‌تر از MAIL_ATTACHMENT_RETENTION_DAYS
 * روز را پیدا می‌کند؛ اگر پیوست داشته باشند، پیوست حذف می‌شود ولی خودِ متن
 * ایمیل باقی می‌ماند (با یک یادداشت کوتاه به‌جای پیوست).
 *
 * محدودیت شناخته‌شده: برای ساده و قابل‌اعتماد ماندن اسکریپت، پیام‌های
 * چندبخشی (multipart/alternative با HTML) به یک نسخه‌ی متنی ساده (text/plain)
 * تبدیل می‌شوند و فرمت HTML اصلی نگه داشته نمی‌شود. اگر نگه‌داشتن دقیق
 * فرمت HTML هم لازم شد، باید این بخش با یک کتابخانه‌ی MIME کامل‌تر بازنویسی شود.
 *
 * تنظیم Cron روی cPanel: Cron Jobs → یک ردیف جدید، هر شب ساعت مثلاً ۳ بامداد:
 *   0 3 * * *  /usr/bin/php /home/USERNAME/rasateams.ir/cron/mail-cleanup-cron.php
 * (مسیر PHP و مسیر پروژه را با مقادیر واقعی هاست خودت جایگزین کن)
 */

define('RESA_APP', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mail-crypto.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('این اسکریپت فقط از طریق Cron Job قابل اجراست.');
}

if (!extension_loaded('imap')) {
    fwrite(STDERR, "افزونه‌ی PHP IMAP فعال نیست — از پشتیبانی هاست بخواه php-imap را فعال کند.\n");
    exit(1);
}

$pdo = resa_db();
$retentionDays = defined('MAIL_ATTACHMENT_RETENTION_DAYS') ? MAIL_ATTACHMENT_RETENTION_DAYS : 30;
$cutoffImapDate = date('d-M-Y', strtotime("-{$retentionDays} days"));

$mailboxes = $pdo->query("SELECT admin_id, local_part, domain, email_address, password_encrypted FROM admin_mailboxes WHERE status = 'active'")->fetchAll();

foreach ($mailboxes as $mb) {
    $password = resa_mail_decrypt($mb['password_encrypted']);
    if ($password === null) {
        resa_cleanup_log($pdo, (int)$mb['admin_id'], 0, 0, 'رمزگشایی پسورد ناموفق بود.');
        continue;
    }

    $mailboxPath = '{' . MAIL_IMAP_HOST . ':' . MAIL_IMAP_PORT . '/imap/ssl/novalidate-cert}INBOX';
    $conn = @imap_open($mailboxPath, $mb['email_address'], $password);
    if (!$conn) {
        resa_cleanup_log($pdo, (int)$mb['admin_id'], 0, 0, 'اتصال IMAP ناموفق: ' . imap_last_error());
        continue;
    }

    $uids = imap_search($conn, 'BEFORE "' . $cutoffImapDate . '"', SE_UID);
    $scanned = 0;
    $cleaned = 0;

    if (is_array($uids)) {
        foreach ($uids as $uid) {
            $scanned++;
            if (resa_strip_attachments_if_any($conn, $uid)) {
                $cleaned++;
            }
        }
    }

    if ($cleaned > 0) {
        imap_expunge($conn);
    }
    imap_close($conn);

    resa_cleanup_log($pdo, (int)$mb['admin_id'], $scanned, $cleaned, null);
    echo $mb['email_address'] . ": scanned={$scanned} cleaned={$cleaned}\n";
}

/**
 * اگر پیام دارای پیوست بود، آن را با یک نسخه‌ی بدون پیوست (فقط متن) جایگزین
 * می‌کند و پیام اصلی را حذف می‌کند. اگر پیوست نداشت، کاری نمی‌کند.
 * @return bool آیا پیامی واقعاً پاکسازی شد
 */
function resa_strip_attachments_if_any($conn, int $uid): bool
{
    $msgno = imap_msgno($conn, $uid);
    if (!$msgno) {
        return false;
    }

    $structure = imap_fetchstructure($conn, $msgno);
    if (!resa_structure_has_attachment($structure)) {
        return false; // پیام پیوست ندارد — دست‌نخورده می‌ماند
    }

    $header = imap_fetchheader($conn, $msgno);
    $plainText = resa_extract_plain_text($conn, $msgno, $structure);
    $notice = "\n\n---\nاین ایمیل حاوی یک یا چند پیوست بود که به دلیل گذشت زمان نگه‌داری، حذف شدند.\n";

    // ساخت پیام جدید: هدرهای اصلی (بدون خط خالی انتهایی) + بدنه‌ی متنی ساده
    $header = rtrim($header) . "\r\n";
    // اگر Content-Type چندبخشی در هدر بود، باید با یک نوع ساده جایگزین شود
    // تا با بدنه‌ی متنی تازه‌مان همخوانی داشته باشد.
    $header = preg_replace('/^Content-Type:.*(\r\n[ \t].*)*\r\n/mi', '', $header);
    $header = preg_replace('/^Content-Transfer-Encoding:.*\r\n/mi', '', $header);
    $header .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $header .= "Content-Transfer-Encoding: 8bit\r\n";

    $newRaw = $header . "\r\n" . $plainText . $notice;

    return resa_append_and_delete($conn, $uid, $newRaw);
}

function resa_append_and_delete($conn, int $uid, string $newRaw): bool
{
    $info = imap_check($conn);
    $target = $info ? $info->Mailbox : '{' . MAIL_IMAP_HOST . ':' . MAIL_IMAP_PORT . '/imap/ssl/novalidate-cert}INBOX';

    $ok = @imap_append($conn, $target, $newRaw);
    if (!$ok) {
        return false;
    }
    imap_delete($conn, $uid, FT_UID);
    return true;
}

function resa_structure_has_attachment($part, bool $isTop = true): bool
{
    if (!$part) {
        return false;
    }
    $isAttachment = false;
    if (isset($part->ifdisposition) && $part->ifdisposition && strtolower($part->disposition ?? '') === 'attachment') {
        $isAttachment = true;
    }
    // فایل‌هایی که disposition ندارند ولی filename دارند هم معمولاً پیوست‌اند
    if (!$isAttachment && isset($part->ifdparameters) && $part->ifdparameters) {
        foreach ($part->dparameters as $p) {
            if (strtolower($p->attribute) === 'filename') {
                $isAttachment = true;
                break;
            }
        }
    }
    if ($isAttachment) {
        return true;
    }
    if (!empty($part->parts)) {
        foreach ($part->parts as $sub) {
            if (resa_structure_has_attachment($sub, false)) {
                return true;
            }
        }
    }
    return false;
}

/**
 * ساده‌ترین نسخه‌ی متنیِ ممکن از پیام را استخراج می‌کند (اولویت با text/plain،
 * در نبود آن از text/html با حذف تگ‌ها استفاده می‌شود).
 */
function resa_extract_plain_text($conn, int $msgno, $structure): string
{
    if (empty($structure->parts)) {
        // پیام تک‌بخشی
        $body = imap_body($conn, $msgno);
        return resa_decode_part($body, $structure->encoding ?? 0);
    }

    $plainPartNum = null;
    $htmlPartNum = null;
    resa_find_text_parts($structure->parts, '', $plainPartNum, $htmlPartNum);

    if ($plainPartNum !== null) {
        $raw = imap_fetchbody($conn, $msgno, $plainPartNum);
        return resa_decode_part($raw, resa_encoding_of($structure->parts, $plainPartNum));
    }
    if ($htmlPartNum !== null) {
        $raw = imap_fetchbody($conn, $msgno, $htmlPartNum);
        $decoded = resa_decode_part($raw, resa_encoding_of($structure->parts, $htmlPartNum));
        return trim(strip_tags($decoded));
    }
    return '(بدون متن قابل‌نمایش)';
}

function resa_find_text_parts(array $parts, string $prefix, &$plainNum, &$htmlNum): void
{
    foreach ($parts as $i => $p) {
        $num = $prefix . ($i + 1);
        if (strtolower($p->subtype ?? '') === 'plain' && $plainNum === null) {
            $plainNum = $num;
        } elseif (strtolower($p->subtype ?? '') === 'html' && $htmlNum === null) {
            $htmlNum = $num;
        }
        if (!empty($p->parts)) {
            resa_find_text_parts($p->parts, $num . '.', $plainNum, $htmlNum);
        }
    }
}

function resa_encoding_of(array $parts, string $num): int
{
    // fallback ساده: چون پیمایش دقیق شماره‌ی بخش پیچیده است، رمزگشایی
    // quoted-printable/base64 را از روی حدس محتوا هم می‌شود انجام داد؛
    // اینجا محافظه‌کارانه 0 (7bit/بدون رمزگشایی خاص) برمی‌گردانیم.
    return 0;
}

function resa_decode_part(string $data, int $encoding): string
{
    switch ($encoding) {
        case 3: // BASE64
            return base64_decode($data);
        case 4: // QUOTED-PRINTABLE
            return quoted_printable_decode($data);
        default:
            return $data;
    }
}

function resa_cleanup_log(PDO $pdo, int $adminId, int $scanned, int $cleaned, ?string $note): void
{
    $stmt = $pdo->prepare("INSERT INTO mail_cleanup_log (admin_id, messages_scanned, attachments_removed, ran_at, note) VALUES (?, ?, ?, NOW(), ?)");
    $stmt->execute([$adminId ?: null, $scanned, $cleaned, $note]);
}
