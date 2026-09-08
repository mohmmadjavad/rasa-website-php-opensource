<?php
/**
 * api/comments_list.php
 * دریافت نظرات تاییدشده‌ی یک وبلاگ (به‌صورت درخت تودرتو) بر اساس اسلاگ یا شناسه وبلاگ.
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
$articleId = isset($_GET['article_id']) ? (int)$_GET['article_id'] : 0;

if ($slug !== '') {
    $stmt = $pdo->prepare("SELECT id FROM articles WHERE slug = ? AND status = 'published' LIMIT 1");
    $stmt->execute([$slug]);
    $articleId = (int)$stmt->fetchColumn();
}

if ($articleId <= 0) {
    respond(false, null, 'وبلاگ یافت نشد.');
}

$stmt = $pdo->prepare("
    SELECT c.id, c.parent_id, c.author_type, c.admin_id, c.user_name, c.content, c.created_at,
           ad.display_name AS admin_display_name, ad.username AS admin_username, ad.avatar AS admin_avatar
    FROM comments c
    LEFT JOIN admins ad ON ad.id = c.admin_id
    WHERE c.article_id = ? AND c.status = 'approved'
    ORDER BY c.created_at ASC
");
$stmt->execute([$articleId]);
$rows = $stmt->fetchAll();

$byId = [];
foreach ($rows as $r) {
    $r['id'] = (int)$r['id'];
    $r['parent_id'] = $r['parent_id'] !== null ? (int)$r['parent_id'] : null;
    if ($r['author_type'] === 'admin') {
        $r['author_name'] = $r['admin_display_name'] ?: $r['admin_username'];
        $r['admin_id'] = $r['admin_id'] !== null ? (int)$r['admin_id'] : null;
    } else {
        $r['author_name'] = $r['user_name'];
        $r['admin_id'] = null;
    }
    unset($r['admin_display_name'], $r['admin_username'], $r['user_name']);
    $r['replies'] = [];
    $byId[$r['id']] = $r;
}

$tree = [];
foreach ($byId as $id => $node) {
    if ($node['parent_id'] !== null && isset($byId[$node['parent_id']])) {
        $byId[$node['parent_id']]['replies'][] = &$byId[$id];
    } else {
        $tree[] = &$byId[$id];
    }
}
unset($node);

respond(true, [
    'article_id' => $articleId,
    'total_count' => count($rows),
    'comments' => $tree,
]);
