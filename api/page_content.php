<?php
/**
 * api/page_content.php
 * اطلاعات صفحه‌های درباره ما و تماس با ما (متن‌ها، اعضای تیم، جوایز) — برای هیدریت شدن صفحات استاتیک با جاوااسکریپت.
 */

define('RESA_APP', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

function respond(bool $ok, $data = null, string $message = ''): void
{
    $out = ['ok' => $ok];
    if ($message !== '') $out['message'] = $message;
    if ($data !== null) $out['data'] = $data;
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = resa_db();
$page = $_GET['page'] ?? 'about';
$page = in_array($page, ['about', 'contact', 'home'], true) ? $page : 'about';

$raw = $page === 'contact' ? resa_get_setting($pdo, 'page_contact', null) : null;
$content = is_array($decoded = json_decode((string)$raw, true)) ? $decoded : [];

$result = ['page' => $page, 'content' => $content];

if ($page === 'about' || $page === 'home') {
    // اعضای تیم دیگر از اینجا خوانده نمی‌شوند؛ صفحات اصلی و درباره ما هر دو از api/team_public.php
    // می‌خوانند تا کارت تیم در هر دو صفحه دقیقاً یکسان باشد (js/team-section.js).
    $awards = $pdo->query("SELECT image, title FROM awards ORDER BY sort_order, id")->fetchAll();
    $result['awards'] = $awards;
}

respond(true, $result);
