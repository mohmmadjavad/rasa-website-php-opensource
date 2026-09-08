<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();
resa_require_blog_api();

$body = api_json_body();
$id = (int)($body['id'] ?? 0);
if ($id <= 0) {
    api_respond(false, null, 'شناسه نامعتبر است.');
}

$stmt = $pdo->prepare("SELECT title, cover_image, author_admin_id FROM articles WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    api_respond(false, null, 'وبلاگ یافت نشد.');
}

if (!resa_can_access_article($row['author_admin_id'] !== null ? (int)$row['author_admin_id'] : null)) {
    api_respond(false, null, 'شما اجازه حذف این وبلاگ را ندارید.');
}

$pdo->prepare("DELETE FROM articles WHERE id = ?")->execute([$id]);

if (!empty($row['cover_image'])) {
    resa_delete_uploaded_image($row['cover_image']);
}

resa_log_activity(
    $pdo,
    resa_current_admin_id(),
    $_SESSION['admin_username'] ?? null,
    'article_delete',
    'حذف وبلاگ «' . $row['title'] . '» (#' . $id . ').'
);

api_respond(true, null, 'وبلاگ حذف شد.');
