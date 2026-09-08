<?php
/**
 * api/project_single.php
 * دریافت یک پروژه منتشرشده بر اساس اسلاگ + افزایش بازدید + تیم پروژه + پروژه‌های مرتبط.
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

$pdo = resa_db();

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') {
    respond(false, null, 'اسلاگ پروژه ارسال نشده است.');
}

$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, c.slug AS category_slug
    FROM projects p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.slug = ? AND p.status = 'published' AND (p.published_at IS NULL OR p.published_at <= NOW())
    LIMIT 1
");
$stmt->execute([$slug]);
$project = $stmt->fetch();

if (!$project) {
    respond(false, null, 'پروژه یافت نشد.');
}

// افزایش بازدید (یک بار در هر نشست برای هر پروژه)
resa_start_session();
$viewedKey = 'viewed_project_' . $project['id'];
if (empty($_SESSION[$viewedKey])) {
    $pdo->prepare("UPDATE projects SET views = views + 1 WHERE id = ?")->execute([$project['id']]);
    $_SESSION[$viewedKey] = true;
    $project['views']++;
}

// تیم پروژه: ادمین‌های سایت
$teamStmt = $pdo->prepare("
    SELECT a.id, a.username, COALESCE(NULLIF(a.display_name,''), a.username) AS name, a.avatar, a.bio,
           pa.sort_order
    FROM project_admins pa JOIN admins a ON a.id = pa.admin_id
    WHERE pa.project_id = ? ORDER BY pa.sort_order ASC
");
$teamStmt->execute([$project['id']]);
$adminTeam = $teamStmt->fetchAll();
foreach ($adminTeam as &$t) {
    $t['id'] = (int)$t['id'];
    $t['is_admin'] = true;
}
unset($t);

// تیم پروژه: اعضای دستی
$memStmt = $pdo->prepare("SELECT id, name, role_title, avatar, sort_order FROM project_members WHERE project_id = ? ORDER BY sort_order ASC, id ASC");
$memStmt->execute([$project['id']]);
$manualTeam = $memStmt->fetchAll();
foreach ($manualTeam as &$m) {
    $m['id'] = (int)$m['id'];
    $m['is_admin'] = false;
}
unset($m);

$team = array_merge($adminTeam, $manualTeam);

// پروژه‌های مرتبط: ابتدا هم‌دسته، سپس پرکن با جدیدترین‌ها
$related = [];
if ($project['category_id']) {
    $relStmt = $pdo->prepare("
        SELECT id, title, slug, excerpt, cover_type, cover_image, cover_video, published_at
        FROM projects
        WHERE status = 'published' AND (published_at IS NULL OR published_at <= NOW()) AND category_id = ? AND id != ?
        ORDER BY published_at DESC LIMIT 3
    ");
    $relStmt->execute([$project['category_id'], $project['id']]);
    $related = $relStmt->fetchAll();
}
if (count($related) < 3) {
    $need = 3 - count($related);
    $excludeIds = array_merge([$project['id']], array_column($related, 'id'));
    $in = implode(',', array_fill(0, count($excludeIds), '?'));
    $fillStmt = $pdo->prepare("
        SELECT id, title, slug, excerpt, cover_type, cover_image, cover_video, published_at
        FROM projects
        WHERE status = 'published' AND (published_at IS NULL OR published_at <= NOW()) AND id NOT IN ($in)
        ORDER BY published_at DESC LIMIT $need
    ");
    $fillStmt->execute($excludeIds);
    $related = array_merge($related, $fillStmt->fetchAll());
}
foreach ($related as &$r) { $r['id'] = (int)$r['id']; }
unset($r);

$project['id'] = (int)$project['id'];
$project['category_id'] = $project['category_id'] !== null ? (int)$project['category_id'] : null;
$project['views'] = (int)$project['views'];
$project['team'] = $team;
$project['related'] = $related;

respond(true, ['project' => $project]);
