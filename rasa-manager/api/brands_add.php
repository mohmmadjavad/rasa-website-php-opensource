<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();
resa_require_manage_project_categories_api();

$name = trim((string)($_POST['name'] ?? ''));
if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    api_respond(false, null, 'نام برند باید بین ۲ تا ۱۰۰ کاراکتر باشد.');
}

$baseSlug = resa_slugify($name);
$slug = resa_unique_slug($pdo, 'brands', $baseSlug);

$logoImage = null;
if (isset($_FILES['logo_image']) && $_FILES['logo_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $upload = resa_upload_image($_FILES['logo_image'], 'brands/logos');
    if (!$upload['ok']) {
        api_respond(false, null, $upload['message']);
    }
    $logoImage = $upload['path'];
}

$stmt = $pdo->prepare("INSERT INTO brands (name, slug, logo_image, created_at) VALUES (?, ?, ?, NOW())");
$stmt->execute([$name, $slug, $logoImage]);

api_respond(true, [
    'id' => (int)$pdo->lastInsertId(),
    'name' => $name,
    'slug' => $slug,
    'logo_image' => $logoImage,
], 'برند اضافه شد.');
