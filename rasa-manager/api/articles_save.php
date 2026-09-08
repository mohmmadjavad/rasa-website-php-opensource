<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();
resa_require_blog_api();

$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : 0;
$isUpdate = $id > 0;

$existing = null;
if ($isUpdate) {
    $chk = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
    $chk->execute([$id]);
    $existing = $chk->fetch();
    if (!$existing) {
        api_respond(false, null, 'وبلاگ موردنظر یافت نشد.');
    }
    if (!resa_can_access_article($existing['author_admin_id'] !== null ? (int)$existing['author_admin_id'] : null)) {
        api_respond(false, null, 'شما اجازه ویرایش این وبلاگ را ندارید.');
    }
}

$title = trim((string)($_POST['title'] ?? ''));
$slugInput = trim((string)($_POST['slug'] ?? ''));
$excerpt = trim((string)($_POST['excerpt'] ?? ''));
$content = resa_sanitize_html((string)($_POST['content'] ?? ''));
$categoryId = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
$status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
$metaTitle = trim((string)($_POST['meta_title'] ?? ''));
$metaDescription = trim((string)($_POST['meta_description'] ?? ''));
$focusKeyword = trim((string)($_POST['focus_keyword'] ?? ''));
$publishedAtInput = trim((string)($_POST['published_at'] ?? ''));
$readingTimeInput = trim((string)($_POST['reading_time'] ?? ''));
$removeCover = (string)($_POST['remove_cover'] ?? '') === '1';

/* ---------- نویسنده: فقط از بین ادمین‌های ثبت‌شده ---------- */
$requestedAuthorId = isset($_POST['author_admin_id']) && $_POST['author_admin_id'] !== '' ? (int)$_POST['author_admin_id'] : 0;
if ((resa_is_super_admin() || resa_blog_scope() === 'all') && $requestedAuthorId > 0) {
    $authorAdminId = $requestedAuthorId;
} else {
    // ادمینی که فقط به وبلاگ‌ها خودش دسترسی دارد، همیشه فقط خودش را می‌تواند نویسنده انتخاب کند
    $authorAdminId = resa_current_admin_id();
}
$authorChk = $pdo->prepare("SELECT id, display_name, username FROM admins WHERE id = ?");
$authorChk->execute([$authorAdminId]);
$authorRow = $authorChk->fetch();
if (!$authorRow) {
    api_respond(false, null, 'نویسنده انتخاب‌شده معتبر نیست.');
}
$author = $authorRow['display_name'] ?: $authorRow['username'];

$subcategoryIds = [];
if (!empty($_POST['subcategory_ids'])) {
    $decoded = json_decode($_POST['subcategory_ids'], true);
    if (is_array($decoded)) {
        $subcategoryIds = array_values(array_unique(array_map('intval', $decoded)));
    }
}

$tagNames = [];
if (!empty($_POST['tags'])) {
    $decoded = json_decode($_POST['tags'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $t) {
            $t = trim((string)$t);
            if ($t !== '' && mb_strlen($t) <= 40) {
                $tagNames[] = $t;
            }
        }
        $tagNames = array_values(array_unique($tagNames));
    }
}

/* ---------- اعتبارسنجی ---------- */
if (mb_strlen($title) < 3 || mb_strlen($title) > 220) {
    api_respond(false, null, 'عنوان باید بین ۳ تا ۲۲۰ کاراکتر باشد.');
}
if (mb_strlen($excerpt) > 500) {
    api_respond(false, null, 'خلاصه نباید بیشتر از ۵۰۰ کاراکتر باشد.');
}
if (mb_strlen($metaTitle) > 180) {
    api_respond(false, null, 'عنوان سئو نباید بیشتر از ۱۸۰ کاراکتر باشد.');
}
if (mb_strlen($metaDescription) > 320) {
    api_respond(false, null, 'توضیحات متا نباید بیشتر از ۳۲۰ کاراکتر باشد.');
}
if (count($tagNames) > 20) {
    api_respond(false, null, 'حداکثر ۲۰ تگ برای هر وبلاگ مجاز است.');
}

if ($categoryId !== null) {
    $catChk = $pdo->prepare("SELECT id, parent_id FROM categories WHERE id = ?");
    $catChk->execute([$categoryId]);
    $catRow = $catChk->fetch();
    if (!$catRow) {
        api_respond(false, null, 'دسته‌بندی اصلی انتخاب‌شده معتبر نیست.');
    }
    if ($catRow['parent_id'] !== null) {
        api_respond(false, null, 'دسته‌بندی اصلی باید یک دسته‌ی سطح‌بالا باشد، نه زیردسته.');
    }
}

if ($subcategoryIds && $categoryId !== null) {
    $in = implode(',', array_fill(0, count($subcategoryIds), '?'));
    $subChk = $pdo->prepare("SELECT id FROM categories WHERE parent_id = ? AND id IN ($in)");
    $subChk->execute(array_merge([$categoryId], $subcategoryIds));
    $validSubIds = array_map('intval', array_column($subChk->fetchAll(), 'id'));
    $subcategoryIds = array_values(array_intersect($subcategoryIds, $validSubIds));
} elseif ($subcategoryIds && $categoryId === null) {
    $subcategoryIds = [];
}

/* ---------- اسلاگ ---------- */
$baseSlug = $slugInput !== '' ? resa_slugify($slugInput) : resa_slugify($title);
$slug = resa_unique_slug($pdo, 'articles', $baseSlug, $isUpdate ? $id : null);

/* ---------- زمان مطالعه ---------- */
if ($readingTimeInput !== '' && is_numeric($readingTimeInput)) {
    $readingTime = max(1, (int)$readingTimeInput);
} else {
    $readingTime = resa_calc_reading_time($content);
}

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

/* ---------- تصویر شاخص ---------- */
$coverImage = $isUpdate ? $existing['cover_image'] : null;
if ($removeCover && $coverImage) {
    resa_delete_uploaded_image($coverImage);
    $coverImage = null;
}
if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $upload = resa_upload_image($_FILES['cover_image'], 'articles/covers');
    if (!$upload['ok']) {
        api_respond(false, null, $upload['message']);
    }
    if ($coverImage) {
        resa_delete_uploaded_image($coverImage);
    }
    $coverImage = $upload['path'];
}

/* ---------- ذخیره‌سازی ---------- */
try {
    $pdo->beginTransaction();

    if ($isUpdate) {
        $stmt = $pdo->prepare("UPDATE articles SET
            title = ?, slug = ?, excerpt = ?, content = ?, cover_image = ?, author = ?, author_admin_id = ?,
            category_id = ?, status = ?, reading_time = ?, meta_title = ?, meta_description = ?,
            focus_keyword = ?, published_at = ?
            WHERE id = ?");
        $stmt->execute([
            $title, $slug, $excerpt, $content, $coverImage, $author, $authorAdminId,
            $categoryId, $status, $readingTime, $metaTitle, $metaDescription,
            $focusKeyword, $publishedAt, $id,
        ]);
        $articleId = $id;
    } else {
        $stmt = $pdo->prepare("INSERT INTO articles
            (title, slug, excerpt, content, cover_image, author, author_admin_id, category_id, status, reading_time,
             meta_title, meta_description, focus_keyword, published_at, views, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())");
        $stmt->execute([
            $title, $slug, $excerpt, $content, $coverImage, $author, $authorAdminId, $categoryId, $status, $readingTime,
            $metaTitle, $metaDescription, $focusKeyword, $publishedAt,
        ]);
        $articleId = (int)$pdo->lastInsertId();
    }

    /* زیردسته‌ها */
    $pdo->prepare("DELETE FROM article_subcategories WHERE article_id = ?")->execute([$articleId]);
    if ($subcategoryIds) {
        $insSub = $pdo->prepare("INSERT INTO article_subcategories (article_id, category_id) VALUES (?, ?)");
        foreach ($subcategoryIds as $sid) {
            $insSub->execute([$articleId, $sid]);
        }
    }

    /* تگ‌ها: پیدا کردن یا ساختن هر تگ */
    $pdo->prepare("DELETE FROM article_tags WHERE article_id = ?")->execute([$articleId]);
    if ($tagNames) {
        $findTag = $pdo->prepare("SELECT id FROM tags WHERE name = ?");
        $insTag = $pdo->prepare("INSERT INTO tags (name, slug, created_at) VALUES (?, ?, NOW())");
        $linkTag = $pdo->prepare("INSERT INTO article_tags (article_id, tag_id) VALUES (?, ?)");
        foreach ($tagNames as $tName) {
            $findTag->execute([$tName]);
            $tagId = $findTag->fetchColumn();
            if (!$tagId) {
                $tSlug = resa_unique_slug($pdo, 'tags', resa_slugify($tName));
                $insTag->execute([$tName, $tSlug]);
                $tagId = (int)$pdo->lastInsertId();
            }
            $linkTag->execute([$articleId, $tagId]);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    api_respond(false, null, 'خطا در ذخیره‌سازی وبلاگ. لطفاً دوباره تلاش کنید.');
}

resa_log_activity(
    $pdo,
    resa_current_admin_id(),
    $_SESSION['admin_username'] ?? null,
    $isUpdate ? 'article_update' : 'article_create',
    ($isUpdate ? 'ویرایش وبلاگ «' : 'ایجاد وبلاگ «') . $title . '» (#' . $articleId . ', وضعیت: ' . ($status === 'published' ? 'منتشرشده' : 'پیش‌نویس') . ').'
);

api_respond(true, [
    'id' => $articleId,
    'slug' => $slug,
    'cover_image' => $coverImage,
    'reading_time' => $readingTime,
], $isUpdate ? 'وبلاگ با موفقیت بروزرسانی شد.' : 'وبلاگ جدید با موفقیت ایجاد شد.');
