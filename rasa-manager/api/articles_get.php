<?php
require_once __DIR__ . '/_bootstrap.php';
resa_require_blog_api();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    api_respond(false, null, 'شناسه نامعتبر است.');
}

$stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
$stmt->execute([$id]);
$article = $stmt->fetch();
if (!$article) {
    api_respond(false, null, 'وبلاگ یافت نشد.');
}

if (!resa_can_access_article($article['author_admin_id'] !== null ? (int)$article['author_admin_id'] : null)) {
    api_respond(false, null, 'شما اجازه دسترسی به این وبلاگ را ندارید.');
}

$subStmt = $pdo->prepare("SELECT category_id FROM article_subcategories WHERE article_id = ?");
$subStmt->execute([$id]);
$subcategoryIds = array_map('intval', array_column($subStmt->fetchAll(), 'category_id'));

$tagStmt = $pdo->prepare("SELECT t.name FROM article_tags at JOIN tags t ON t.id = at.tag_id WHERE at.article_id = ?");
$tagStmt->execute([$id]);
$tags = array_column($tagStmt->fetchAll(), 'name');

$article['id'] = (int)$article['id'];
$article['category_id'] = $article['category_id'] !== null ? (int)$article['category_id'] : null;
$article['author_admin_id'] = $article['author_admin_id'] !== null ? (int)$article['author_admin_id'] : null;
$article['reading_time'] = (int)$article['reading_time'];
$article['views'] = (int)$article['views'];
$article['subcategory_ids'] = $subcategoryIds;
$article['tags'] = $tags;

api_respond(true, ['article' => $article]);
