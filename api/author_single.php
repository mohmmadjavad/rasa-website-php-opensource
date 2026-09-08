<?php
/**
 * api/author_single.php
 * پروفایل عمومی هر ادمین (صفحه resume.html) — هدر پروفایل + خلاصه وبلاگ‌ها/پروژه‌ها.
 * فهرست کامل وبلاگ‌ها از api/articles_list.php?author=ID و فهرست کامل پروژه‌ها از
 * api/projects_list.php?author=ID (مسونری/پینترستی) خوانده می‌شود.
 */

define('RESA_APP', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/team-helpers.php';

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

$username = trim((string)($_GET['user'] ?? ''));
if ($username === '') {
    respond(false, null, 'شناسه پروفایل نامعتبر است.');
}

$stmt = $pdo->prepare("SELECT id, username, display_name, avatar, card_image, job_title, bio_short, bio_full,
    social_telegram, social_instagram, social_whatsapp, social_github, social_email, social_phone, social_linkedin, social_x, social_youtube, social_website, social_pinterest, social_custom_label, social_custom_url
    FROM admins WHERE username = ?");
$stmt->execute([$username]);
$author = $stmt->fetch();

if (!$author) {
    respond(false, null, 'این عضو یافت نشد.');
}

$id = (int)$author['id'];

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM articles WHERE author_admin_id = ? AND status = 'published' AND published_at <= NOW()");
$countStmt->execute([$id]);
$articleCount = (int)$countStmt->fetchColumn();

$viewsStmt = $pdo->prepare("SELECT COALESCE(SUM(views),0) FROM articles WHERE author_admin_id = ? AND status = 'published' AND published_at <= NOW()");
$viewsStmt->execute([$id]);
$totalViews = (int)$viewsStmt->fetchColumn();

$projectCountStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT p.id) FROM projects p
    LEFT JOIN project_admins pa ON pa.project_id = p.id
    WHERE p.status = 'published' AND (p.created_by_admin_id = ? OR pa.admin_id = ?)
");
$projectCountStmt->execute([$id, $id]);
$projectCount = (int)$projectCountStmt->fetchColumn();

$author['id'] = (int)$author['id'];
$author['display_name'] = $author['display_name'] ?: $author['username'];
$author['socials'] = resa_social_list_from_row($author);
$author['article_count'] = $articleCount;
$author['project_count'] = $projectCount;
$author['total_views'] = $totalViews;
unset($author['username']);

respond(true, ['author' => $author]);
