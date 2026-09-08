<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();
resa_require_super_admin_api();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    api_respond(false, null, 'شناسه نامعتبر است.');
}

$existingStmt = $pdo->prepare("SELECT * FROM brands WHERE id = ?");
$existingStmt->execute([$id]);
$existing = $existingStmt->fetch();
if (!$existing) {
    api_respond(false, null, 'برند موردنظر یافت نشد.');
}

$name = trim((string)($_POST['name'] ?? ''));
if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    api_respond(false, null, 'نام برند باید بین ۲ تا ۱۰۰ کاراکتر باشد.');
}

if ($name !== $existing['name']) {
    $baseSlug = resa_slugify($name);
    $slug = resa_unique_slug($pdo, 'brands', $baseSlug, $id);
} else {
    $slug = $existing['slug'];
}

$logoImage = $existing['logo_image'];
if (isset($_FILES['logo_image']) && $_FILES['logo_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $upload = resa_upload_image($_FILES['logo_image'], 'brands/logos');
    if (!$upload['ok']) {
        api_respond(false, null, $upload['message']);
    }
    if ($logoImage) resa_delete_uploaded_image($logoImage);
    $logoImage = $upload['path'];
} elseif (!empty($_POST['remove_logo'])) {
    if ($logoImage) resa_delete_uploaded_image($logoImage);
    $logoImage = null;
}

$stmt = $pdo->prepare("UPDATE brands SET name = ?, slug = ?, logo_image = ? WHERE id = ?");
$stmt->execute([$name, $slug, $logoImage, $id]);

api_respond(true, [
    'id' => $id,
    'name' => $name,
    'slug' => $slug,
    'logo_image' => $logoImage,
], 'برند بروزرسانی شد.');
