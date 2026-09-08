<?php
/**
 * api/taxonomy_list.php
 * دسته‌بندی‌ها (به‌همراه زیردسته) و تگ‌های پرکاربرد در وبلاگ‌ها منتشرشده — برای فیلتر صفحه وبلاگ‌ها.
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

$catStmt = $pdo->query("
    SELECT c.id, c.name, c.slug, c.parent_id, c.icon_image, c.icon_image AS icon, c.poster_image, c.description,
           COUNT(DISTINCT a.id) AS article_count
    FROM categories c
    LEFT JOIN articles a ON (a.category_id = c.id OR a.id IN (
        SELECT article_id FROM article_subcategories WHERE category_id = c.id
    )) AND a.status = 'published' AND a.published_at <= NOW()
    GROUP BY c.id, c.name, c.slug, c.parent_id, c.icon_image, c.poster_image, c.description
    HAVING article_count > 0
    ORDER BY (c.parent_id IS NOT NULL), c.parent_id, c.name
");
$categories = $catStmt->fetchAll();
foreach ($categories as &$c) {
    $c['id'] = (int)$c['id'];
    $c['parent_id'] = $c['parent_id'] !== null ? (int)$c['parent_id'] : null;
    $c['article_count'] = (int)$c['article_count'];
}
unset($c);

$tagStmt = $pdo->query("
    SELECT t.id, t.name, t.slug, COUNT(DISTINCT at.article_id) AS article_count
    FROM tags t
    JOIN article_tags at ON at.tag_id = t.id
    JOIN articles a ON a.id = at.article_id AND a.status = 'published' AND a.published_at <= NOW()
    GROUP BY t.id, t.name, t.slug
    HAVING article_count > 0
    ORDER BY article_count DESC, t.name
    LIMIT 24
");
$tags = $tagStmt->fetchAll();
foreach ($tags as &$t) {
    $t['id'] = (int)$t['id'];
    $t['article_count'] = (int)$t['article_count'];
}
unset($t);

$authorStmt = $pdo->query("
    SELECT ad.id, COALESCE(NULLIF(ad.display_name, ''), ad.username) AS name, ad.avatar,
           COUNT(DISTINCT a.id) AS article_count
    FROM admins ad
    JOIN articles a ON a.author_admin_id = ad.id AND a.status = 'published' AND a.published_at <= NOW()
    GROUP BY ad.id, name, ad.avatar
    HAVING article_count > 0
    ORDER BY article_count DESC, name
");
$authors = $authorStmt->fetchAll();
foreach ($authors as &$au) {
    $au['id'] = (int)$au['id'];
    $au['article_count'] = (int)$au['article_count'];
}
unset($au);

respond(true, ['categories' => $categories, 'tags' => $tags, 'authors' => $authors]);
