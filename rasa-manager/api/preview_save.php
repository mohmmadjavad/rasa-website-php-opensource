<?php
/**
 * admin/api/preview_save.php
 * محتوای فعلیِ فرم ویرایشگر (چه ذخیره شده باشد چه نه) را موقتاً در نشست
 * (Session) همین ادمین ذخیره می‌کند تا صفحه‌ی عمومی سایت بتواند آن را با
 * یک توکن یک‌بارمصرف، دقیقاً با همان قالب سایت واقعی نمایش دهد — بدون
 * اینکه چیزی در دیتابیس ذخیره یا منتشر شود.
 */
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();

$body = api_json_body();
$type = ($body['type'] ?? '') === 'project' ? 'project' : 'article';

$title = trim((string)($body['title'] ?? ''));
if ($title === '') {
    api_respond(false, null, 'برای پیش‌نمایش، عنوان را وارد کنید.');
}

$token = bin2hex(random_bytes(16));

$data = [
    'id'                => 0,
    'title'             => $title,
    'slug'              => trim((string)($body['slug'] ?? '')) ?: 'preview',
    'excerpt'           => trim((string)($body['excerpt'] ?? '')),
    'content'           => (string)($body['content'] ?? ''),
    'cover_image'       => $type === 'article' ? (trim((string)($body['cover_image'] ?? '')) ?: null) : null,
    'cover_type'        => $type === 'project' ? (($body['cover_type'] ?? 'image') === 'video' ? 'video' : 'image') : null,
    'cover_video'       => $type === 'project' ? (trim((string)($body['cover_video'] ?? '')) ?: null) : null,
    'author'            => trim((string)($body['author'] ?? '')) ?: 'رسا تیم',
    'author_profile'    => null,
    'category_name'     => trim((string)($body['category_name'] ?? '')) ?: null,
    'category_slug'     => trim((string)($body['category_slug'] ?? '')) ?: null,
    'category_icon'     => null,
    'category_description' => null,
    'published_at'      => trim((string)($body['published_at'] ?? '')) ?: date('Y-m-d H:i:s'),
    'created_at'        => date('Y-m-d H:i:s'),
    'reading_time'      => (int)($body['reading_time'] ?? 1) ?: 1,
    'views'             => 0,
    'meta_title'        => trim((string)($body['meta_title'] ?? '')),
    'meta_description'  => trim((string)($body['meta_description'] ?? '')),
    'tags'              => is_array($body['tags'] ?? null) ? $body['tags'] : [],
    'subcategories'     => [],
    'team'              => [],
    'related'           => [],
];
// اگر تصویر کاور به‌صورت data: URL ارسال شده (تصویر تازه انتخاب‌شده و هنوز آپلود نشده)، همان‌طور نگه می‌داریم.
if ($type === 'project') {
    $data['cover_image'] = trim((string)($body['cover_image'] ?? '')) ?: null;
}

if (isset($body['cover_image']) && strpos((string)$body['cover_image'], 'data:') === 0) {
    $data['cover_image'] = $body['cover_image'];
}

$_SESSION['resa_preview_' . $type] = [
    'token' => $token,
    'data'  => $data,
];

api_respond(true, ['token' => $token]);
