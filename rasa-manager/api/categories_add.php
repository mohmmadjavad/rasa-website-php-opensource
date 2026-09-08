<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();

$type = ($_POST['type'] ?? 'blog') === 'project' ? 'project' : 'blog';
// ساخت دسته‌بندی اصلی/فرعی نیازمند دسترسی «ساخت دسته‌بندی» متناسب با بخش (وبلاگ یا پروژه) است.
if ($type === 'project') {
    resa_require_manage_project_categories_api();
} else {
    resa_require_manage_categories_api();
}

$name = trim((string)($_POST['name'] ?? ''));
$parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== ''
    ? (int)$_POST['parent_id']
    : null;
$description = trim((string)($_POST['description'] ?? ''));

if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    api_respond(false, null, 'نام دسته‌بندی باید بین ۲ تا ۱۰۰ کاراکتر باشد.');
}
if (mb_strlen($description) > 500) {
    api_respond(false, null, 'توضیح دسته‌بندی نباید بیشتر از ۵۰۰ کاراکتر باشد.');
}

if ($parentId !== null) {
    $chk = $pdo->prepare("SELECT parent_id FROM categories WHERE id = ? AND type = ?");
    $chk->execute([$parentId, $type]);
    $parentRow = $chk->fetch();
    if (!$parentRow) {
        api_respond(false, null, 'دسته‌بندی اصلی انتخاب‌شده یافت نشد.');
    }
    if ($parentRow['parent_id'] !== null) {
        api_respond(false, null, 'زیردسته را نمی‌توان زیرمجموعه‌ی یک زیردسته دیگر قرار داد.');
    }
}

// آیکون و پوستر فقط برای دسته‌بندی‌های اصلی (سطح بالا) معنا دارند
$iconImage = null;
$posterImage = null;
if ($parentId === null) {
    if (isset($_FILES['icon_image']) && $_FILES['icon_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload = resa_upload_image($_FILES['icon_image'], 'categories/icons');
        if (!$upload['ok']) {
            api_respond(false, null, $upload['message']);
        }
        $iconImage = $upload['path'];
    }
    if (isset($_FILES['poster_image']) && $_FILES['poster_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload = resa_upload_image($_FILES['poster_image'], 'categories/posters');
        if (!$upload['ok']) {
            if ($iconImage) resa_delete_uploaded_image($iconImage);
            api_respond(false, null, $upload['message']);
        }
        $posterImage = $upload['path'];
    }
} else {
    $description = '';
}

$baseSlug = resa_slugify($name);
$slug = resa_unique_slug($pdo, 'categories', $baseSlug);

$stmt = $pdo->prepare("INSERT INTO categories (name, slug, parent_id, icon_image, poster_image, description, type, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
$stmt->execute([$name, $slug, $parentId, $iconImage, $posterImage, $description ?: null, $type]);

api_respond(true, [
    'id' => (int)$pdo->lastInsertId(),
    'name' => $name,
    'slug' => $slug,
    'parent_id' => $parentId,
    'icon_image' => $iconImage,
    'poster_image' => $posterImage,
    'description' => $description ?: null,
], $parentId ? 'زیردسته اضافه شد.' : 'دسته‌بندی اصلی اضافه شد.');
