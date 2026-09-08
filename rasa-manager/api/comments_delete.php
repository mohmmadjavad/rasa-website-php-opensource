<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();
resa_require_comments_api();

$body = api_json_body();
$id = (int)($body['id'] ?? 0);
if ($id <= 0) {
    api_respond(false, null, 'شناسه نظر نامعتبر است.');
}

$stmt = $pdo->prepare("SELECT c.id, a.author_admin_id FROM comments c JOIN articles a ON a.id = c.article_id WHERE c.id = ? LIMIT 1");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    api_respond(false, null, 'نظر یافت نشد.');
}
if (!resa_can_access_article_comments($row['author_admin_id'] !== null ? (int)$row['author_admin_id'] : null)) {
    resa_api_forbidden('شما دسترسی حذف این نظر را ندارید.');
}

$del = $pdo->prepare("DELETE FROM comments WHERE id = ?");
$del->execute([$id]);

resa_log_activity($pdo, resa_current_admin_id(), $_SESSION['admin_username'] ?? null, 'comment_delete', 'نظر #' . $id . ' حذف شد.');

api_respond(true, null, 'نظر و پاسخ‌های آن حذف شد.');
