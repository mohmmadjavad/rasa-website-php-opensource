<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();
resa_require_super_admin_api();

$body = api_json_body();
$id = (int)($body['id'] ?? 0);
if ($id <= 0) {
    api_respond(false, null, 'شناسه نامعتبر است.');
}

$stmt = $pdo->prepare("SELECT image, title FROM awards WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    api_respond(false, null, 'جایزه یافت نشد.');
}

$del = $pdo->prepare("DELETE FROM awards WHERE id = ?");
$del->execute([$id]);
resa_delete_uploaded_image($row['image']);

resa_log_activity($pdo, resa_current_admin_id(), $_SESSION['admin_username'] ?? null, 'award_delete', 'حذف جایزه' . ($row['title'] ? (': ' . $row['title']) : ''));
api_respond(true, null, 'جایزه حذف شد.');
