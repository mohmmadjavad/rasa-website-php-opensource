<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();
resa_require_super_admin_api();

$title = trim((string)($_POST['title'] ?? ''));
$sortOrder = (int)($_POST['sort_order'] ?? 0);

if (mb_strlen($title) > 160) {
    api_respond(false, null, 'عنوان طولانی است.');
}
if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
    api_respond(false, null, 'تصویر جایزه الزامی است.');
}

$upload = resa_upload_image($_FILES['image'], 'awards');
if (!$upload['ok']) {
    api_respond(false, null, $upload['message']);
}

$stmt = $pdo->prepare("INSERT INTO awards (image, title, sort_order, created_at) VALUES (?, ?, ?, NOW())");
$stmt->execute([$upload['path'], $title ?: null, $sortOrder]);

resa_log_activity($pdo, resa_current_admin_id(), $_SESSION['admin_username'] ?? null, 'award_add', 'افزودن جایزه' . ($title ? (': ' . $title) : ''));
api_respond(true, ['id' => (int)$pdo->lastInsertId(), 'image' => $upload['path']], 'جایزه اضافه شد.');
