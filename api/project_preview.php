<?php
/**
 * api/project_preview.php
 * نسخه‌ی «پیش‌نمایش زنده»ی project_single.php برای محتوای ذخیره‌نشده‌ی
 * ویرایشگر پروژه — همان منطق article_preview.php.
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
$stored = $_SESSION['resa_preview_project'] ?? null;

if (!$token || !$stored || !hash_equals($stored['token'], $token)) {
    respond(false, null, 'پیش‌نمایش منقضی شده یا نامعتبر است. دوباره از پنل ادمین امتحان کنید.');
}

respond(true, ['project' => $stored['data']]);
