<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();
resa_require_super_admin_api();

$body = api_json_body();
$id = (int)($body['id'] ?? 0);
if ($id <= 0) {
    api_respond(false, null, 'شناسه نامعتبر است.');
}

$existingStmt = $pdo->prepare("SELECT logo_image FROM brands WHERE id = ?");
$existingStmt->execute([$id]);
$existing = $existingStmt->fetch();
if ($existing && $existing['logo_image']) {
    resa_delete_uploaded_image($existing['logo_image']);
}

$stmt = $pdo->prepare("DELETE FROM brands WHERE id = ?");
$stmt->execute([$id]);

api_respond(true, null, 'برند حذف شد.');
