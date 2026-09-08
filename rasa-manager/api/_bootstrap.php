<?php
/**
 * admin/api/_bootstrap.php
 * فایل مشترک برای همه‌ی endpoint های API پنل ادمین.
 */

define('RESA_APP', true);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/articles_helpers.php';
require_once __DIR__ . '/../../includes/backup-helpers.php';
require_once __DIR__ . '/../../includes/team-helpers.php';
require_once __DIR__ . '/../../includes/cpanel-api.php';
require_once __DIR__ . '/../../includes/mail-crypto.php';
require_once __DIR__ . '/../../includes/mail-helpers.php';
require_once __DIR__ . '/../../includes/webmail-sso.php';

header('Content-Type: application/json; charset=utf-8');
resa_require_login_api();
$pdo = resa_db();
resa_sync_admin_session($pdo, true);

function api_respond(bool $ok, $data = null, string $message = ''): void
{
    $out = ['ok' => $ok];
    if ($message !== '') $out['message'] = $message;
    if ($data !== null)  $out['data'] = $data;
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

function api_require_csrf(): void
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $token = $headers['X-CSRF-Token'] ?? $headers['X-Csrf-Token'] ?? ($_POST['csrf'] ?? '');
    if (!resa_csrf_check($token)) {
        http_response_code(403);
        api_respond(false, null, 'نشست منقضی شده، صفحه را رفرش کنید.');
    }
}

function api_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
