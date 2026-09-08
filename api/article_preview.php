<?php
/**
 * api/article_preview.php
 * نسخه‌ی «پیش‌نمایش زنده»ی article_single.php: به‌جای خواندن از دیتابیس با
 * اسلاگ، محتوای ذخیره‌شده در نشست ادمین (توسط admin/api/preview_save.php)
 * را برمی‌گرداند — فقط وقتی توکن معتبر باشد و کاربر همان ادمین لاگین‌کرده
 * باشد. هیچ داده‌ای در دیتابیس ذخیره یا منتشر نمی‌شود.
 */

define('RESA_APP', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

function respond(bool $ok, $data = null, string $message = ''): void
{
    $out = ['ok' => $ok];
    if ($message !== '') $out['message'] = $message;
    if ($data !== null) $out['data'] = $data;
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

resa_start_session();

if (empty($_SESSION['admin_id'])) {
    respond(false, null, 'برای مشاهده‌ی پیش‌نمایش باید وارد پنل ادمین شده باشید.');
}

$token = trim((string)($_GET['token'] ?? ''));
$stored = $_SESSION['resa_preview_article'] ?? null;

if (!$token || !$stored || !hash_equals($stored['token'], $token)) {
    respond(false, null, 'پیش‌نمایش منقضی شده یا نامعتبر است. دوباره از پنل ادمین امتحان کنید.');
}

respond(true, ['article' => $stored['data']]);
