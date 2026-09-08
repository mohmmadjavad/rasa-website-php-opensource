<?php
require_once __DIR__ . '/_bootstrap.php';
resa_require_messages_view_api();
api_require_csrf();

$body = api_json_body();
$id = (int)($body['id'] ?? 0);
$read = !empty($body['read']) ? 1 : 0;

if ($id <= 0) {
    api_respond(false, null, 'شناسه نامعتبر است.');
}

$stmt = $pdo->prepare("UPDATE messages SET is_read = ? WHERE id = ?");
$stmt->execute([$read, $id]);

$unreadStmt = $pdo->query("SELECT COUNT(*) FROM messages WHERE is_read = 0");
$unread = (int)$unreadStmt->fetchColumn();

api_respond(true, ['unread_count' => $unread]);
