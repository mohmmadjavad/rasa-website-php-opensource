<?php
require_once __DIR__ . '/_bootstrap.php';
resa_require_comments_api();

$q         = trim((string)($_GET['q'] ?? ''));
$status    = trim((string)($_GET['status'] ?? ''));
$articleId = isset($_GET['article_id']) && $_GET['article_id'] !== '' ? (int)$_GET['article_id'] : 0;

/* صفحه‌بندی: تعداد نظرات معمولاً چند برابر تعداد وبلاگ‌هاست، پس بدون
   صفحه‌بندی این لیست زودتر از همه سنگین می‌شود. */
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(1, min(100, (int)($_GET['per_page'] ?? 20)));
$offset = ($page - 1) * $perPage;

$canSeeAll = resa_is_super_admin() || resa_comments_scope() === 'all';
$currentAdminId = resa_current_admin_id();

$where = [];
$params = [];

if (!$canSeeAll) {
    // این ادمین فقط نظرات وبلاگ‌ها خودش را می‌بیند
    $where[] = "a.author_admin_id = ?";
    $params[] = $currentAdminId;
}
if ($articleId > 0) {
    $where[] = "c.article_id = ?";
    $params[] = $articleId;
}
if ($status === 'pending' || $status === 'approved' || $status === 'rejected') {
    $where[] = "c.status = ?";
    $params[] = $status;
}
if ($q !== '') {
    $where[] = "(c.content LIKE ? OR c.user_name LIKE ? OR a.title LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM comments c JOIN articles a ON a.id = c.article_id $whereSql");
$countStmt->execute($params);
$filteredTotal = (int)$countStmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT c.id, c.article_id, c.parent_id, c.author_type, c.admin_id, c.user_name, c.content,
           c.status, c.spam_reason, c.created_at,
           a.title AS article_title, a.slug AS article_slug, a.author_admin_id,
           ad.display_name AS admin_display_name, ad.username AS admin_username,
           pc.user_name AS parent_user_name, pc.author_type AS parent_author_type,
           pad.display_name AS parent_admin_display_name, pad.username AS parent_admin_username
    FROM comments c
    JOIN articles a ON a.id = c.article_id
    LEFT JOIN admins ad ON ad.id = c.admin_id
    LEFT JOIN comments pc ON pc.id = c.parent_id
    LEFT JOIN admins pad ON pad.id = pc.admin_id
    $whereSql
    ORDER BY c.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$scopeSql = $canSeeAll ? '' : "JOIN articles a2 ON a2.id = c2.article_id AND a2.author_admin_id = " . (int)$currentAdminId;
$counts = $pdo->query("SELECT c2.status, COUNT(*) cnt FROM comments c2 $scopeSql GROUP BY c2.status")->fetchAll();
$statCounts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($counts as $c) {
    $statCounts[$c['status']] = (int)$c['cnt'];
}

foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['article_id'] = (int)$r['article_id'];
    $r['parent_id'] = $r['parent_id'] !== null ? (int)$r['parent_id'] : null;
    $r['admin_id'] = $r['admin_id'] !== null ? (int)$r['admin_id'] : null;
    $r['author_admin_id'] = $r['author_admin_id'] !== null ? (int)$r['author_admin_id'] : null;
    $r['author_name'] = $r['author_type'] === 'admin' ? ($r['admin_display_name'] ?: $r['admin_username']) : $r['user_name'];
    $r['can_manage'] = resa_can_access_article_comments($r['author_admin_id']);
    if ($r['parent_id']) {
        $r['parent_author_name'] = $r['parent_author_type'] === 'admin'
            ? ($r['parent_admin_display_name'] ?: $r['parent_admin_username'])
            : $r['parent_user_name'];
    } else {
        $r['parent_author_name'] = null;
    }
    unset($r['admin_display_name'], $r['admin_username'], $r['parent_user_name'], $r['parent_author_type'], $r['parent_admin_display_name'], $r['parent_admin_username']);
}
unset($r);

api_respond(true, [
    'comments' => $rows,
    'total_count' => $statCounts['pending'] + $statCounts['approved'] + $statCounts['rejected'],
    'pending_count' => $statCounts['pending'],
    'approved_count' => $statCounts['approved'],
    'rejected_count' => $statCounts['rejected'],
    'page' => $page,
    'per_page' => $perPage,
    'filtered_total' => $filteredTotal,
    'total_pages' => (int)max(1, ceil($filteredTotal / $perPage)),
]);
