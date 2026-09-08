<?php
/**
 * api/article_single.php
 * دریافت یک وبلاگ منتشرشده بر اساس اسلاگ + افزایش بازدید + وبلاگ‌ها مرتبط.
 */

define('RESA_APP', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
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

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') {
    respond(false, null, 'اسلاگ وبلاگ ارسال نشده است.');
}

$stmt = $pdo->prepare("
    SELECT a.*, c.name AS category_name, c.slug AS category_slug,
           c.description AS category_description, c.icon_image AS category_icon
    FROM articles a
    LEFT JOIN categories c ON c.id = a.category_id
    WHERE a.slug = ? AND a.status = 'published' AND a.published_at <= NOW()
    LIMIT 1
");
$stmt->execute([$slug]);
$article = $stmt->fetch();

if (!$article) {
    respond(false, null, 'وبلاگ یافت نشد.');
}

// افزایش بازدید (یک بار در هر نشست برای هر وبلاگ)
resa_start_session();
$viewedKey = 'viewed_article_' . $article['id'];
if (empty($_SESSION[$viewedKey])) {
    $pdo->prepare("UPDATE articles SET views = views + 1 WHERE id = ?")->execute([$article['id']]);
    $_SESSION[$viewedKey] = true;
    $article['views']++;
}

$subStmt = $pdo->prepare("
    SELECT c.id, c.name, c.slug FROM article_subcategories asub
    JOIN categories c ON c.id = asub.category_id
    WHERE asub.article_id = ?
");
$subStmt->execute([$article['id']]);
$subcategories = $subStmt->fetchAll();

$tagStmt = $pdo->prepare("SELECT t.name, t.slug FROM article_tags at JOIN tags t ON t.id = at.tag_id WHERE at.article_id = ?");
$tagStmt->execute([$article['id']]);
$tags = $tagStmt->fetchAll();

// پروفایل کامل نویسنده برای «درباره نویسنده»
$authorProfile = null;
if (!empty($article['author_admin_id'])) {
    $authStmt = $pdo->prepare("SELECT id, username, display_name, avatar, job_title, bio_short, bio_full,
        social_telegram, social_instagram, social_whatsapp, social_github, social_email, social_phone, social_linkedin, social_x, social_youtube, social_website, social_pinterest, social_custom_label, social_custom_url
        FROM admins WHERE id = ?");
    $authStmt->execute([$article['author_admin_id']]);
    $authorProfile = $authStmt->fetch() ?: null;
    if ($authorProfile) {
        $authorProfile['id'] = (int)$authorProfile['id'];
        $authorProfile['display_name'] = $authorProfile['display_name'] ?: $authorProfile['username'];
        $authorProfile['socials'] = resa_social_list_from_row($authorProfile);
        $article['author'] = $authorProfile['display_name'];
    }
}

// وبلاگ‌ها مرتبط: ابتدا هم‌دسته، سپس پرکن با جدیدترین‌ها
$related = [];
if ($article['category_id']) {
    $relStmt = $pdo->prepare("
        SELECT id, title, slug, excerpt, cover_image, reading_time, published_at
        FROM articles
        WHERE status = 'published' AND published_at <= NOW() AND category_id = ? AND id != ?
        ORDER BY published_at DESC LIMIT 3
    ");
    $relStmt->execute([$article['category_id'], $article['id']]);
    $related = $relStmt->fetchAll();
}
if (count($related) < 3) {
    $need = 3 - count($related);
    $excludeIds = array_merge([$article['id']], array_column($related, 'id'));
    $in = implode(',', array_fill(0, count($excludeIds), '?'));
    $fillStmt = $pdo->prepare("
        SELECT id, title, slug, excerpt, cover_image, reading_time, published_at
        FROM articles
        WHERE status = 'published' AND published_at <= NOW() AND id NOT IN ($in)
        ORDER BY published_at DESC LIMIT $need
    ");
    $fillStmt->execute($excludeIds);
    $related = array_merge($related, $fillStmt->fetchAll());
}

foreach ($related as &$r) {
    $r['id'] = (int)$r['id'];
    $r['reading_time'] = (int)$r['reading_time'];
}
unset($r);

$article['id'] = (int)$article['id'];
$article['category_id'] = $article['category_id'] !== null ? (int)$article['category_id'] : null;
$article['author_admin_id'] = $article['author_admin_id'] !== null ? (int)$article['author_admin_id'] : null;
$article['reading_time'] = (int)$article['reading_time'];
$article['views'] = (int)$article['views'];
$article['subcategories'] = $subcategories;
$article['tags'] = $tags;
$article['related'] = $related;
$article['author_profile'] = $authorProfile;

respond(true, ['article' => $article]);