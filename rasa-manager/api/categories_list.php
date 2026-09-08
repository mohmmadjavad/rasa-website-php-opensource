<?php
require_once __DIR__ . '/_bootstrap.php';

// اگر type ارسال نشود، برای سازگاری با کد قبلی، دسته‌بندی‌های وبلاگ برگردانده می‌شود.
$type = ($_GET['type'] ?? 'blog') === 'project' ? 'project' : 'blog';
$countCol = $type === 'project' ? 'project_count' : 'article_count';
$countTable = $type === 'project' ? 'projects' : 'articles';

$stmt = $pdo->prepare("
    SELECT c.id, c.name, c.slug, c.parent_id, c.icon_image, c.poster_image, c.description,
           (SELECT COUNT(*) FROM `$countTable` t WHERE t.category_id = c.id) AS item_count
    FROM categories c
    WHERE c.type = ?
    ORDER BY (c.parent_id IS NOT NULL), c.parent_id, c.name
");
$stmt->execute([$type]);
$rows = $stmt->fetchAll();
foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['parent_id'] = $r['parent_id'] !== null ? (int)$r['parent_id'] : null;
    $r['article_count'] = (int)$r['item_count'];
    $r[$countCol] = (int)$r['item_count'];
    unset($r['item_count']);
}
unset($r);

api_respond(true, ['categories' => $rows]);
