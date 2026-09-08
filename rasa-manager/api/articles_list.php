<?php
require_once __DIR__ . '/_bootstrap.php';
resa_require_blog_api();

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : 0;
$authorId = isset($_GET['author_id']) && $_GET['author_id'] !== '' ? (int)$_GET['author_id'] : 0;

/* صفحه‌بندی: با رشد تعداد وبلاگ‌ها به چند هزار مورد، خواندن همه‌ی ردیف‌ها در
   یک درخواست هم دیتابیس و هم مرورگر را کند می‌کند؛ پس همیشه صفحه‌به‌صفحه می‌خوانیم. */
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(1, min(100, (int)($_GET['per_page'] ?? 20)));
$offset = ($page - 1) * $perPage;

$canSeeAll = resa_is_super_admin() || resa_blog_scope() === 'all';
$currentAdminId = resa_current_admin_id();

$where = [];
$params = [];

if (!$canSeeAll) {
    // این ادمین فقط وبلاگ‌ها خودش را می‌بیند
    $where[] = "a.author_admin_id = ?";
    $params[] = $currentAdminId;
} elseif ($authorId > 0) {
    $where[] = "a.author_admin_id = ?";
    $params[] = $authorId;
}

if ($q !== '') {
    $where[] = "(a.title LIKE ? OR a.slug LIKE ? OR a.author LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($status === 'draft' || $status === 'published') {
    $where[] = "a.status = ?";
    $params[] = $status;
}
if ($categoryId > 0) {
    $where[] = "a.category_id = ?";
    $params[] = $categoryId;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM articles a $whereSql");
$countStmt->execute($params);
$filteredTotal = (int)$countStmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT a.id, a.title, a.slug, a.author, a.author_admin_id, a.status, a.cover_image, a.reading_time,
           a.views, a.published_at, a.created_at, a.updated_at,
           c.id AS category_id, c.name AS category_name
    FROM articles a
    LEFT JOIN categories c ON c.id = a.category_id
    $whereSql
    ORDER BY a.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

if ($rows) {
    $ids = array_column($rows, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $tagStmt = $pdo->prepare("SELECT at.article_id, t.name FROM article_tags at JOIN tags t ON t.id = at.tag_id WHERE at.article_id IN ($in)");
    $tagStmt->execute($ids);
    $tagsByArticle = [];
    foreach ($tagStmt->fetchAll() as $tr) {
        $tagsByArticle[$tr['article_id']][] = $tr['name'];
    }
} else {
    $tagsByArticle = [];
}

$scopeSql = $canSeeAll ? '' : 'WHERE author_admin_id = ' . (int)$currentAdminId;
$counts = $pdo->query("SELECT status, COUNT(*) c FROM articles $scopeSql GROUP BY status")->fetchAll();
$statCounts = ['draft' => 0, 'published' => 0];
foreach ($counts as $c) {
    $statCounts[$c['status']] = (int)$c['c'];
}

foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['category_id'] = $r['category_id'] !== null ? (int)$r['category_id'] : null;
    $r['author_admin_id'] = $r['author_admin_id'] !== null ? (int)$r['author_admin_id'] : null;
    $r['reading_time'] = (int)$r['reading_time'];
    $r['views'] = (int)$r['views'];
    $r['tags'] = $tagsByArticle[$r['id']] ?? [];
}
unset($r);

api_respond(true, [
    'articles' => $rows,
    'total_count' => $statCounts['draft'] + $statCounts['published'],
    'published_count' => $statCounts['published'],
    'draft_count' => $statCounts['draft'],
    'page' => $page,
    'per_page' => $perPage,
    'filtered_total' => $filteredTotal,
    'total_pages' => (int)max(1, ceil($filteredTotal / $perPage)),
]);
