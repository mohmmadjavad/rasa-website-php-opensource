<?php
/**
 * api/comments_submit.php
 * ثبت نظر جدید یا پاسخ کاربر روی یک وبلاگ.
 */

define('RESA_APP', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/spam-filter.php';

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

// --- محدودیت نرخ ارسال: حداقل ۱۵ ثانیه بین دو نظر از یک نشست ---
if (!empty($_SESSION['last_comment_submit']) && (time() - $_SESSION['last_comment_submit']) < 15) {
    respond(false, 'لطفاً کمی صبر کنید و دوباره تلاش کنید.');
}

$pdo = resa_db();

// --- محدودیت نرخ بر اساس IP (مستقل از سشن، حداکثر ۱۰ نظر در ۱۰ دقیقه) ---
try {
    if (!resa_rate_limit_ok($pdo, 'comment_submit', 10, 600)) {
        respond(false, 'تعداد درخواست‌های شما بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.');
    }
} catch (Throwable $e) {
    // اگر خود بررسی نرخ به هر دلیلی خطا داد، اجازه‌ی ادامه بده تا کاربر واقعی مسدود نشود
}

$articleId   = (int)($_POST['article_id'] ?? 0);
$parentId    = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
$userName    = trim((string)($_POST['user_name'] ?? ''));
$content     = trim((string)($_POST['content'] ?? ''));
$captchaInput = trim((string)($_POST['captcha'] ?? ''));

$errors = [];

$artStmt = $pdo->prepare("SELECT id FROM articles WHERE id = ? AND status = 'published' LIMIT 1");
$artStmt->execute([$articleId]);
if (!$artStmt->fetchColumn()) {
    respond(false, 'وبلاگ یافت نشد.');
}

if ($parentId !== null) {
    $pStmt = $pdo->prepare("SELECT id FROM comments WHERE id = ? AND article_id = ? LIMIT 1");
    $pStmt->execute([$parentId, $articleId]);
    if (!$pStmt->fetchColumn()) {
        $parentId = null;
    }
}

if (mb_strlen($userName) < 2 || mb_strlen($userName) > 100) {
    $errors['user_name'] = 'نام باید بین ۲ تا ۱۰۰ کاراکتر باشد.';
}
if (mb_strlen($content) < 3 || mb_strlen($content) > 2000) {
    $errors['content'] = 'متن نظر باید بین ۳ تا ۲۰۰۰ کاراکتر باشد.';
}
if (empty($_SESSION['comment_captcha']) || empty($_SESSION['comment_captcha_time']) || (time() - $_SESSION['comment_captcha_time']) > 600) {
    $errors['captcha'] = 'کد امنیتی منقضی شده است، دوباره تلاش کنید.';
} elseif ($captchaInput === '' || $captchaInput !== $_SESSION['comment_captcha']) {
    $errors['captcha'] = 'کد امنیتی صحیح نیست.';
}

if (!empty($errors)) {
    unset($_SESSION['comment_captcha']);
    respond(false, 'لطفاً خطاهای فرم را بررسی کنید.', ['errors' => $errors]);
}

$autoApprove = resa_get_setting($pdo, 'comments_auto_approve', '1') === '1';
$spamReason = resa_detect_comment_spam($content, $userName);
$status = ($autoApprove && !$spamReason) ? 'approved' : 'pending';

try {
    $stmt = $pdo->prepare("INSERT INTO comments (article_id, parent_id, author_type, user_name, content, status, spam_reason, ip_address, created_at)
        VALUES (?, ?, 'user', ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$articleId, $parentId, $userName, $content, $status, $spamReason, resa_client_ip()]);

    unset($_SESSION['comment_captcha']);
    $_SESSION['last_comment_submit'] = time();

    respond(true, $status === 'approved' ? 'نظر شما با موفقیت ثبت شد.' : 'نظر شما ثبت شد و پس از تایید ادمین نمایش داده می‌شود.', [
        'auto_approved' => $status === 'approved',
        'comment_id' => (int)$pdo->lastInsertId(),
    ]);
} catch (Throwable $e) {
    respond(false, 'خطایی در ثبت نظر رخ داد. لطفاً بعداً دوباره تلاش کنید.');
}
