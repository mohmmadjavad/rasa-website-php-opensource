<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();
resa_require_comments_api();

$body = api_json_body();
$id = (int)($body['id'] ?? 0);
$status = (string)($body['status'] ?? '');

if ($id <= 0) {
    api_respond(false, null, 'شناسه نظر نامعتبر است.');
}
if (!in_array($status, ['approved', 'pending', 'rejected'], true)) {
    api_respond(false, null, 'وضعیت نامعتبر است.');
}

$stmt = $pdo->prepare("SELECT c.id, a.author_admin_id FROM comments c JOIN articles a ON a.id = c.article_id WHERE c.id = ? LIMIT 1");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    api_respond(false, null, 'نظر یافت نشد.');
}
if (!resa_can_access_article_comments($row['author_admin_id'] !== null ? (int)$row['author_admin_id'] : null)) {
    resa_api_forbidden('شما دسترسی مدیریت این نظر را ندارید.');
}

$upd = $pdo->prepare("UPDATE comments SET status = ? WHERE id = ?");
$upd->execute([$status, $id]);

$statusLabel = ['approved' => 'تایید شد', 'pending' => 'در انتظار بررسی', 'rejected' => 'رد شد'][$status] ?? $status;
resa_log_activity($pdo, resa_current_admin_id(), $_SESSION['admin_username'] ?? null, 'comment_status_change', 'وضعیت نظر #' . $id . ' به «' . $statusLabel . '» تغییر کرد.');

api_respond(true, null, 'وضعیت نظر بروزرسانی شد.');
