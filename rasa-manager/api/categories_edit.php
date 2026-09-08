<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();
resa_require_super_admin_api();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    api_respond(false, null, 'شناسه نامعتبر است.');
}

$existingStmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$existingStmt->execute([$id]);
$existing = $existingStmt->fetch();
if (!$existing) {
    api_respond(false, null, 'دسته‌بندی موردنظر یافت نشد.');
}
$isMain = $existing['parent_id'] === null;

$name = trim((string)($_POST['name'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$removeIcon = (string)($_POST['remove_icon'] ?? '') === '1';
$removePoster = (string)($_POST['remove_poster'] ?? '') === '1';

if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    api_respond(false, null, 'نام دسته‌بندی باید بین ۲ تا ۱۰۰ کاراکتر باشد.');
}
if (mb_strlen($description) > 500) {
    api_respond(false, null, 'توضیح دسته‌بندی نباید بیشتر از ۵۰۰ کاراکتر باشد.');
}
if (!$isMain) {
    $description = '';
}

$iconImage = $existing['icon_image'];
$posterImage = $existing['poster_image'];

if ($isMain) {
    if ($removeIcon && $iconImage) {
        resa_delete_uploaded_image($iconImage);
        $iconImage = null;
    }
    if ($removePoster && $posterImage) {
        resa_delete_uploaded_image($posterImage);
        $posterImage = null;
    }
    if (isset($_FILES['icon_image']) && $_FILES['icon_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload = resa_upload_image($_FILES['icon_image'], 'categories/icons');
        if (!$upload['ok']) {
            api_respond(false, null, $upload['message']);
        }
        if ($iconImage) resa_delete_uploaded_image($iconImage);
        $iconImage = $upload['path'];
    }
    if (isset($_FILES['poster_image']) && $_FILES['poster_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload = resa_upload_image($_FILES['poster_image'], 'categories/posters');
        if (!$upload['ok']) {
            api_respond(false, null, $upload['message']);
        }
        if ($posterImage) resa_delete_uploaded_image($posterImage);
        $posterImage = $upload['path'];
    }
} else {
    $iconImage = null;
    $posterImage = null;
}

// اسلاگ فقط در صورت تغییر نام دوباره ساخته می‌شود
if ($name !== $existing['name']) {
    $baseSlug = resa_slugify($name);
    $slug = resa_unique_slug($pdo, 'categories', $baseSlug, $id);
} else {
    $slug = $existing['slug'];
}

$stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, icon_image = ?, poster_image = ?, description = ? WHERE id = ?");
$stmt->execute([$name, $slug, $iconImage, $posterImage, $description ?: null, $id]);

api_respond(true, [
    'id' => $id,
    'name' => $name,
    'slug' => $slug,
    'icon_image' => $iconImage,
    'poster_image' => $posterImage,
    'description' => $description ?: null,
], 'دسته‌بندی بروزرسانی شد.');
