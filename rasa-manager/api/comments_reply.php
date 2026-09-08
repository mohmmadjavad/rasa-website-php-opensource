<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();
resa_require_comments_api();

$body = api_json_body();
$articleId = (int)($body['article_id'] ?? 0);
$parentId  = isset($body['parent_id']) && $body['parent_id'] !== '' ? (int)$body['parent_id'] : null;
$content   = trim((string)($body['content'] ?? ''));

if (mb_strlen($content) < 1 || mb_strlen($content) > 2000) {
    api_respond(false, null, 'متن پاسخ باید بین ۱ تا ۲۰۰۰ کاراکتر باشد.');
}

$artStmt = $pdo->prepare("SELECT id, author_admin_id FROM articles WHERE id = ? LIMIT 1");
$artStmt->execute([$articleId]);
$article = $artStmt->fetch();
if (!$article) {
    api_respond(false, null, 'وبلاگ یافت نشد.');
}
if (!resa_can_access_article_comments($article['author_admin_id'] !== null ? (int)$article['author_admin_id'] : null)) {
    resa_api_forbidden('شما دسترسی پاسخ‌دهی به نظرات این وبلاگ را ندارید.');
}

if ($parentId !== null) {
    $pStmt = $pdo->prepare("SELECT id FROM comments WHERE id = ? AND article_id = ? LIMIT 1");
    $pStmt->execute([$parentId, $articleId]);
    if (!$pStmt->fetchColumn()) {
        api_respond(false, null, 'نظر مبدا یافت نشد.');
    }
}

$stmt = $pdo->prepare("INSERT INTO comments (article_id, parent_id, author_type, admin_id, content, status, ip_address, created_at)
    VALUES (?, ?, 'admin', ?, ?, 'approved', ?, NOW())");
$stmt->execute([$articleId, $parentId, resa_current_admin_id(), $content, resa_client_ip()]);

api_respond(true, ['id' => (int)$pdo->lastInsertId()], 'پاسخ شما ثبت شد.');
