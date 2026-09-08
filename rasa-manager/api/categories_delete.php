<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();
resa_require_super_admin_api();

$body = api_json_body();
$id = (int)($body['id'] ?? 0);
if ($id <= 0) {
    api_respond(false, null, 'شناسه نامعتبر است.');
}

$imgStmt = $pdo->prepare("SELECT icon_image, poster_image FROM categories WHERE id = ?");
$imgStmt->execute([$id]);
$imgRow = $imgStmt->fetch();

$stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
$stmt->execute([$id]);

if ($imgRow) {
    resa_delete_uploaded_image($imgRow['icon_image']);
    resa_delete_uploaded_image($imgRow['poster_image']);
}

api_respond(true, null, 'دسته‌بندی حذف شد.');
