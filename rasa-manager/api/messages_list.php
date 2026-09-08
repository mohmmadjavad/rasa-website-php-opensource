<?php
require_once __DIR__ . '/_bootstrap.php';
resa_require_messages_view_api();

/* صفحه‌بندی: پیام‌های تماس با ما هم می‌توانند در طول زمان به چند هزار مورد
   برسند؛ خواندن همه‌ی آن‌ها در یک درخواست را با صفحه‌بندی جایگزین می‌کنیم. */
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(1, min(100, (int)($_GET['per_page'] ?? 20)));
$offset = ($page - 1) * $perPage;

$total = (int)$pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
$unread = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE is_read = 0")->fetchColumn();

$stmt = $pdo->prepare("SELECT id, full_name, contact_info, message, is_read, created_at FROM messages ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute();
$rows = $stmt->fetchAll();

foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['is_read'] = (bool)$r['is_read'];
}
unset($r);

api_respond(true, [
    'messages' => $rows,
    'unread_count' => $unread,
    'total_count' => $total,
    'page' => $page,
    'per_page' => $perPage,
    'total_pages' => (int)max(1, ceil($total / $perPage)),
]);
