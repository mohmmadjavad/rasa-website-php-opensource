<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();
resa_require_projects_api();

$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : 0;
$isUpdate = $id > 0;

$existing = null;
$existingTeamIds = [];
if ($isUpdate) {
    $chk = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $chk->execute([$id]);
    $existing = $chk->fetch();
    if (!$existing) {
        api_respond(false, null, 'پروژه موردنظر یافت نشد.');
    }
    $teamChk = $pdo->prepare("SELECT admin_id FROM project_admins WHERE project_id = ?");
    $teamChk->execute([$id]);
    $existingTeamIds = array_map('intval', array_column($teamChk->fetchAll(), 'admin_id'));
    if (!resa_can_access_project($existingTeamIds)) {
        api_respond(false, null, 'شما اجازه ویرایش این پروژه را ندارید.');
    }
}

$title = trim((string)($_POST['title'] ?? ''));
$slugInput = trim((string)($_POST['slug'] ?? ''));
$excerpt = trim((string)($_POST['excerpt'] ?? ''));
$content = resa_sanitize_html((string)($_POST['content'] ?? ''));
$categoryIdsInput = [];
if (!empty($_POST['category_ids'])) {
    $decodedCats = json_decode($_POST['category_ids'], true);
    if (is_array($decodedCats)) {
        $categoryIdsInput = array_values(array_unique(array_map('intval', $decodedCats)));
    }
} elseif (isset($_POST['category_id']) && $_POST['category_id'] !== '') {
    // سازگاری با فراخوانی‌های قدیمی‌تر که فقط یک دسته می‌فرستادند
    $categoryIdsInput = [(int)$_POST['category_id']];
}
$brandIdsInput = [];
if (!empty($_POST['brand_ids'])) {
    $decodedBrands = json_decode($_POST['brand_ids'], true);
    if (is_array($decodedBrands)) {
        $brandIdsInput = array_values(array_unique(array_map('intval', $decodedBrands)));
    }
}
$status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
$metaTitle = trim((string)($_POST['meta_title'] ?? ''));
$metaDescription = trim((string)($_POST['meta_description'] ?? ''));
$publishedAtInput = trim((string)($_POST['published_at'] ?? ''));
$displayScope = (string)($_POST['display_scope'] ?? 'both');
if (!in_array($displayScope, ['projects_page', 'profile_only', 'both'], true)) {
    $displayScope = 'both';
}
$coverType = ($_POST['cover_type'] ?? 'image') === 'video' ? 'video' : 'image';
$removeCover = (string)($_POST['remove_cover'] ?? '') === '1';
$coverVideoUrl = trim((string)($_POST['cover_video_url'] ?? ''));

/* ---------- اعتبارسنجی پایه ---------- */
if (mb_strlen($title) < 3 || mb_strlen($title) > 200) {
    api_respond(false, null, 'عنوان باید بین ۳ تا ۲۰۰ کاراکتر باشد.');
}
if (mb_strlen($excerpt) > 500) {
    api_respond(false, null, 'خلاصه نباید بیشتر از ۵۰۰ کاراکتر باشد.');
}
if (mb_strlen($metaTitle) > 180 || mb_strlen($metaDescription) > 320) {
    api_respond(false, null, 'مقادیر سئو خارج از محدوده مجاز است.');
}

$validCategoryIds = [];
if ($categoryIdsInput) {
    $in = implode(',', array_fill(0, count($categoryIdsInput), '?'));
    $catChk = $pdo->prepare("SELECT id FROM categories WHERE type = 'project' AND id IN ($in)");
    $catChk->execute($categoryIdsInput);
    $validCategoryIds = array_map('intval', array_column($catChk->fetchAll(), 'id'));
    if (count($validCategoryIds) !== count($categoryIdsInput)) {
        api_respond(false, null, 'یکی از دسته‌بندی‌های انتخاب‌شده معتبر نیست.');
    }
}
// category_id همچنان به‌عنوان دسته‌بندی اصلی/پیش‌فرض نگه داشته می‌شود (سازگاری با نمایش عمومی سایت)
$categoryId = $validCategoryIds ? $validCategoryIds[0] : null;

/* برندها: فیلد فقط برای نمایش «صفحه پروژه‌ها» یا «صفحه پروژه‌ها + پروفایل اعضا» معنا دارد */
$validBrandIds = [];
if ($brandIdsInput && $displayScope !== 'profile_only') {
    $in = implode(',', array_fill(0, count($brandIdsInput), '?'));
    $brandChk = $pdo->prepare("SELECT id FROM brands WHERE id IN ($in)");
    $brandChk->execute($brandIdsInput);
    $validBrandIds = array_map('intval', array_column($brandChk->fetchAll(), 'id'));
}

/* ---------- تیم پروژه: ادمین‌ها (فقط ادمین‌هایی که پروفایل‌شان تکمیل شده) ---------- */
$requestedAdminIds = [];
if (!empty($_POST['admin_ids'])) {
    $decoded = json_decode($_POST['admin_ids'], true);
    if (is_array($decoded)) {
        $requestedAdminIds = array_values(array_unique(array_map('intval', $decoded)));
    }
}
// ادمینی که فقط دسترسی own دارد، همیشه خودش هم باید جزو تیم باشد
if (!resa_is_super_admin() && resa_projects_scope() !== 'all') {
    if (!in_array(resa_current_admin_id(), $requestedAdminIds, true)) {
        $requestedAdminIds[] = resa_current_admin_id();
    }
}

$validAdminIds = [];
if ($requestedAdminIds) {
    $in = implode(',', array_fill(0, count($requestedAdminIds), '?'));
    $adminChk = $pdo->prepare("SELECT id, display_name FROM admins WHERE id IN ($in)");
    $adminChk->execute($requestedAdminIds);
    $foundAdmins = $adminChk->fetchAll();
    foreach ($foundAdmins as $fa) {
        if (empty(trim((string)($fa['display_name'] ?? '')))) {
            api_respond(false, null, 'یکی از ادمین‌های انتخاب‌شده هنوز پروفایل خود (نام نمایشی) را تکمیل نکرده و نمی‌تواند به تیم پروژه اضافه شود.');
        }
        $validAdminIds[] = (int)$fa['id'];
    }
}

/* ---------- اعضای دستی تیم (بدون حساب ادمین) ---------- */
$membersInput = [];
if (!empty($_POST['members'])) {
    $decoded = json_decode($_POST['members'], true);
    if (is_array($decoded)) {
        $membersInput = $decoded;
    }
}
if (count($membersInput) > 40) {
    api_respond(false, null, 'حداکثر ۴۰ عضو تیم مجاز است.');
}

/* ---------- اسلاگ ---------- */
$baseSlug = $slugInput !== '' ? resa_slugify($slugInput) : resa_slugify($title);
$slug = resa_unique_slug($pdo, 'projects', $baseSlug, $isUpdate ? $id : null);

/* ---------- تاریخ انتشار ---------- */
$publishedAt = null;
if ($publishedAtInput !== '') {
    $ts = strtotime($publishedAtInput);
    if ($ts !== false) {
        $publishedAt = date('Y-m-d H:i:s', $ts);
    }
}
if ($status === 'published' && $publishedAt === null) {
    $publishedAt = $isUpdate && $existing['published_at'] ? $existing['published_at'] : date('Y-m-d H:i:s');
}

/* ---------- رسانه شاخص (عکس یا ویدیو) ---------- */
$coverImage = $isUpdate ? $existing['cover_image'] : null;
$coverVideo = $isUpdate ? $existing['cover_video'] : null;

if ($removeCover) {
    if ($coverImage) resa_delete_uploaded_image($coverImage);
    if ($coverVideo) resa_delete_uploaded_image($coverVideo);
    $coverImage = null;
    $coverVideo = null;
}

if ($coverType === 'image') {
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload = resa_upload_image($_FILES['cover_image'], 'projects/covers');
        if (!$upload['ok']) {
            api_respond(false, null, $upload['message']);
        }
        if ($coverImage) resa_delete_uploaded_image($coverImage);
        $coverImage = $upload['path'];
    }
    if ($coverVideo) { resa_delete_uploaded_image($coverVideo); $coverVideo = null; }
} else {
    if (isset($_FILES['cover_video_file']) && $_FILES['cover_video_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload = resa_upload_media($_FILES['cover_video_file'], 'projects/covers', 'video');
        if (!$upload['ok']) {
            api_respond(false, null, $upload['message']);
        }
        if ($coverVideo && strpos($coverVideo, 'assets/uploads/') === 0) resa_delete_uploaded_image($coverVideo);
        $coverVideo = $upload['path'];
    } elseif ($coverVideoUrl !== '') {
        if ($coverVideo && strpos($coverVideo, 'assets/uploads/') === 0) resa_delete_uploaded_image($coverVideo);
        $coverVideo = $coverVideoUrl;
    }
    if ($coverImage) { resa_delete_uploaded_image($coverImage); $coverImage = null; }
}

/* ---------- ذخیره‌سازی ---------- */
try {
    $pdo->beginTransaction();

    if ($isUpdate) {
        $stmt = $pdo->prepare("UPDATE projects SET
            title = ?, slug = ?, excerpt = ?, content = ?, cover_type = ?, cover_image = ?, cover_video = ?,
            category_id = ?, status = ?, meta_title = ?, meta_description = ?, published_at = ?, display_scope = ?
            WHERE id = ?");
        $stmt->execute([
            $title, $slug, $excerpt, $content, $coverType, $coverImage, $coverVideo,
            $categoryId, $status, $metaTitle, $metaDescription, $publishedAt, $displayScope, $id,
        ]);
        $projectId = $id;
    } else {
        $stmt = $pdo->prepare("INSERT INTO projects
            (title, slug, excerpt, content, cover_type, cover_image, cover_video, category_id, status,
             meta_title, meta_description, created_by_admin_id, published_at, display_scope, views, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())");
        $stmt->execute([
            $title, $slug, $excerpt, $content, $coverType, $coverImage, $coverVideo, $categoryId, $status,
            $metaTitle, $metaDescription, resa_current_admin_id(), $publishedAt, $displayScope,
        ]);
        $projectId = (int)$pdo->lastInsertId();
    }

    /* دسته‌بندی‌ها (چند-انتخابی) */
    $pdo->prepare("DELETE FROM project_categories WHERE project_id = ?")->execute([$projectId]);
    if ($validCategoryIds) {
        $insCat = $pdo->prepare("INSERT IGNORE INTO project_categories (project_id, category_id) VALUES (?, ?)");
        foreach ($validCategoryIds as $cid) {
            $insCat->execute([$projectId, $cid]);
        }
    }

    /* برندها (چند-انتخابی) */
    $pdo->prepare("DELETE FROM project_brands WHERE project_id = ?")->execute([$projectId]);
    if ($validBrandIds) {
        $insBrand = $pdo->prepare("INSERT IGNORE INTO project_brands (project_id, brand_id) VALUES (?, ?)");
        foreach ($validBrandIds as $bid) {
            $insBrand->execute([$projectId, $bid]);
        }
    }

    /* تیم: ادمین‌ها */
    $pdo->prepare("DELETE FROM project_admins WHERE project_id = ?")->execute([$projectId]);
    if ($validAdminIds) {
        $insTeam = $pdo->prepare("INSERT INTO project_admins (project_id, admin_id, sort_order) VALUES (?, ?, ?)");
        foreach (array_values($validAdminIds) as $i => $aid) {
            $insTeam->execute([$projectId, $aid, $i]);
        }
    }

    /* تیم: اعضای دستی — آواتارهای قبلی را نگه می‌داریم، رکوردها را دوباره می‌سازیم */
    $oldMemStmt = $pdo->prepare("SELECT id, avatar FROM project_members WHERE project_id = ?");
    $oldMemStmt->execute([$projectId]);
    $oldAvatarsById = [];
    foreach ($oldMemStmt->fetchAll() as $om) {
        $oldAvatarsById[(int)$om['id']] = $om['avatar'];
    }

    $keptAvatarPaths = [];
    $newMembers = [];

    foreach ($membersInput as $i => $m) {
        $mName = trim((string)($m['name'] ?? ''));
        if ($mName === '' || mb_strlen($mName) > 150) continue;
        $mRole = trim((string)($m['role_title'] ?? ''));
        if (mb_strlen($mRole) > 150) $mRole = mb_substr($mRole, 0, 150);

        $mAvatar = null;
        $existingId = isset($m['id']) ? (int)$m['id'] : 0;
        if ($existingId > 0 && isset($oldAvatarsById[$existingId])) {
            $mAvatar = $oldAvatarsById[$existingId];
        }

        $fileKey = 'member_avatar_' . $i;
        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload = resa_upload_image($_FILES[$fileKey], 'projects/members');
            if ($upload['ok']) {
                $mAvatar = $upload['path'];
            }
        } elseif (!empty($m['remove_avatar'])) {
            $mAvatar = null;
        }

        if ($mAvatar) $keptAvatarPaths[] = $mAvatar;
        $newMembers[] = [$projectId, $mName, $mRole ?: null, $mAvatar, count($newMembers)];
    }

    $pdo->prepare("DELETE FROM project_members WHERE project_id = ?")->execute([$projectId]);
    if ($newMembers) {
        $insMem = $pdo->prepare("INSERT INTO project_members (project_id, name, role_title, avatar, sort_order, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        foreach ($newMembers as $nm) {
            $insMem->execute($nm);
        }
    }

    $pdo->commit();

    // حذف آواتارهای قدیمی که دیگر استفاده نمی‌شوند
    foreach ($oldAvatarsById as $oldPath) {
        if ($oldPath && !in_array($oldPath, $keptAvatarPaths, true)) {
            resa_delete_uploaded_image($oldPath);
        }
    }
} catch (Throwable $e) {
    $pdo->rollBack();
    api_respond(false, null, 'خطا در ذخیره‌سازی پروژه. لطفاً دوباره تلاش کنید.');
}

resa_log_activity(
    $pdo,
    resa_current_admin_id(),
    $_SESSION['admin_username'] ?? null,
    $isUpdate ? 'project_update' : 'project_create',
    ($isUpdate ? 'ویرایش پروژه «' : 'ایجاد پروژه «') . $title . '» (#' . $projectId . ', وضعیت: ' . ($status === 'published' ? 'منتشرشده' : 'پیش‌نویس') . ').'
);

api_respond(true, [
    'id' => $projectId,
    'slug' => $slug,
], $isUpdate ? 'پروژه با موفقیت بروزرسانی شد.' : 'پروژه جدید با موفقیت ایجاد شد.');
