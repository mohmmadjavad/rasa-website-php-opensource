<?php
/**
 * api/articles_list.php
 * فهرست عمومی وبلاگ‌ها منتشرشده — با فیلتر دسته‌بندی، تگ، جستجو و صفحه‌بندی.
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

$isStack = !empty($_GET['stack']);
$isTop = !empty($_GET['top']);

if ($isStack || $isTop) {
    $limit = max(1, min(20, (int)($_GET['limit'] ?? 5)));
    $orderBy = $isTop ? 'a.views DESC, a.published_at DESC' : 'a.published_at DESC, a.id DESC';

    $stmt = $pdo->prepare("
        SELECT a.id, a.title, a.slug, a.excerpt, a.cover_image,
               COALESCE(NULLIF(ad.display_name, ''), a.author) AS author,
               a.author_admin_id, a.reading_time,
               a.published_at, a.views, c.name AS category_name, c.slug AS category_slug,
               c.description AS category_description, c.icon_image AS category_icon,
               ad.avatar AS author_avatar, ad.username AS author_username
        FROM articles a
        LEFT JOIN categories c ON c.id = a.category_id
        LEFT JOIN admins ad ON ad.id = a.author_admin_id
        WHERE a.status = 'published' AND a.published_at <= NOW()
        ORDER BY $orderBy
        LIMIT $limit
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['author_admin_id'] = $r['author_admin_id'] !== null ? (int)$r['author_admin_id'] : null;
        $r['reading_time'] = (int)$r['reading_time'];
        $r['views'] = (int)$r['views'];
    }
    unset($r);

    respond(true, ['articles' => $rows]);
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9;
$offset = ($page - 1) * $perPage;

$q = trim((string)($_GET['q'] ?? ''));
$categorySlug = trim((string)($_GET['category'] ?? ''));
$subcategorySlug = trim((string)($_GET['subcategory'] ?? ''));
$tagSlug = trim((string)($_GET['tag'] ?? ''));
$authorId = isset($_GET['author']) && $_GET['author'] !== '' ? (int)$_GET['author'] : 0;

$sortKey = trim((string)($_GET['sort'] ?? 'newest'));
$sortMap = [
    'newest'       => 'a.published_at DESC, a.id DESC',
    'oldest'       => 'a.published_at ASC, a.id ASC',
    'views'        => 'a.views DESC, a.published_at DESC',
    'reading_time' => 'a.reading_time ASC, a.published_at DESC',
];
$orderBySql = $sortMap[$sortKey] ?? $sortMap['newest'];

$where = ["a.status = 'published'", "a.published_at <= NOW()"];
$params = [];

if ($q !== '') {
    $where[] = "(a.title LIKE ? OR a.excerpt LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like;
}

if ($authorId > 0) {
    $where[] = "a.author_admin_id = ?";
    $params[] = $authorId;
}

$categoryJoin = '';
if ($categorySlug !== '') {
    $categoryJoin = "JOIN categories fc ON (fc.id = a.category_id OR fc.id IN (SELECT category_id FROM article_subcategories WHERE article_id = a.id))";
    $where[] = "fc.slug = ?";
    $params[] = $categorySlug;
}

if ($subcategorySlug !== '') {
    $where[] = "a.id IN (SELECT article_id FROM article_subcategories asub JOIN categories sc ON sc.id = asub.category_id WHERE sc.slug = ?)";
    $params[] = $subcategorySlug;
}

$tagJoin = '';
if ($tagSlug !== '') {
    $tagJoin = "JOIN article_tags ft ON ft.article_id = a.id JOIN tags ftg ON ftg.id = ft.tag_id";
    $where[] = "ftg.slug = ?";
    $params[] = $tagSlug;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(DISTINCT a.id) FROM articles a $categoryJoin $tagJoin $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT DISTINCT a.id, a.title, a.slug, a.excerpt, a.cover_image,
           COALESCE(NULLIF(ad.display_name, ''), a.author) AS author,
           a.author_admin_id, a.reading_time,
           a.published_at, a.views, c.name AS category_name, c.slug AS category_slug,
           c.description AS category_description, c.icon_image AS category_icon,
           ad.avatar AS author_avatar, ad.username AS author_username
    FROM articles a
    LEFT JOIN categories c ON c.id = a.category_id
    LEFT JOIN admins ad ON ad.id = a.author_admin_id
    $categoryJoin $tagJoin
    $whereSql
    ORDER BY $orderBySql
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

if ($rows) {
    $ids = array_column($rows, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $tagStmt = $pdo->prepare("SELECT at.article_id, t.name, t.slug FROM article_tags at JOIN tags t ON t.id = at.tag_id WHERE at.article_id IN ($in)");
    $tagStmt->execute($ids);
    $tagsByArticle = [];
    foreach ($tagStmt->fetchAll() as $tr) {
        $tagsByArticle[$tr['article_id']][] = ['name' => $tr['name'], 'slug' => $tr['slug']];
    }
} else {
    $tagsByArticle = [];
}

foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['author_admin_id'] = $r['author_admin_id'] !== null ? (int)$r['author_admin_id'] : null;
    $r['reading_time'] = (int)$r['reading_time'];
    $r['views'] = (int)$r['views'];
    $r['tags'] = $tagsByArticle[$r['id']] ?? [];
}
unset($r);

respond(true, [
    'articles' => $rows,
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
    'total_pages' => (int)ceil($total / $perPage),
]);