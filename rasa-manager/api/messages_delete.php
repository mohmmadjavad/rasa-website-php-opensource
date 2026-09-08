<?php
require_once __DIR__ . '/_bootstrap.php';
resa_require_messages_delete_api();
api_require_csrf();

$body = api_json_body();
$id = (int)($body['id'] ?? 0);

if ($id <= 0) {
    api_respond(false, null, 'شناسه نامعتبر است.');
}

$stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
$stmt->execute([$id]);

$unreadStmt = $pdo->query("SELECT COUNT(*) FROM messages WHERE is_read = 0");
$unread = (int)$unreadStmt->fetchColumn();

resa_log_activity($pdo, resa_current_admin_id(), $_SESSION['admin_username'] ?? null, 'message_delete', 'پیام تماس با ما با شناسه #' . $id . ' حذف شد.');

api_respond(true, ['unread_count' => $unread], 'پیام حذف شد.');
