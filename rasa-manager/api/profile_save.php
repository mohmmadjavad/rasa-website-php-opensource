<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();

$adminId = resa_current_admin_id();

$displayName = trim((string)($_POST['display_name'] ?? ''));
$jobTitle = trim((string)($_POST['job_title'] ?? ''));
$bioShort = trim((string)($_POST['bio_short'] ?? ''));
$bioFull = trim((string)($_POST['bio_full'] ?? ''));
$telegram = trim((string)($_POST['social_telegram'] ?? ''));
$instagram = trim((string)($_POST['social_instagram'] ?? ''));
$whatsapp = trim((string)($_POST['social_whatsapp'] ?? ''));
$github = trim((string)($_POST['social_github'] ?? ''));
$email = trim((string)($_POST['social_email'] ?? ''));
$phone = trim((string)($_POST['social_phone'] ?? ''));
$linkedin = trim((string)($_POST['social_linkedin'] ?? ''));
$x = trim((string)($_POST['social_x'] ?? ''));
$youtube = trim((string)($_POST['social_youtube'] ?? ''));
$website = trim((string)($_POST['social_website'] ?? ''));
$pinterest = trim((string)($_POST['social_pinterest'] ?? ''));
$customLabel = trim((string)($_POST['social_custom_label'] ?? ''));
$customUrl = trim((string)($_POST['social_custom_url'] ?? ''));
$removeAvatar = (string)($_POST['remove_avatar'] ?? '') === '1';
$removeCardImage = (string)($_POST['remove_card_image'] ?? '') === '1';

if (mb_strlen($displayName) > 100) {
    api_respond(false, null, 'نام نمایشی نباید بیشتر از ۱۰۰ کاراکتر باشد.');
}
if (mb_strlen($jobTitle) > 160) {
    api_respond(false, null, 'عنوان شغلی نباید بیشتر از ۱۶۰ کاراکتر باشد.');
}
if (mb_strlen($bioShort) > 240) {
    api_respond(false, null, 'بیوگرافی خلاصه نباید بیشتر از ۲۴۰ کاراکتر باشد.');
}
if (mb_strlen($bioFull) > 4000) {
    api_respond(false, null, 'بیوگرافی مفصل نباید بیشتر از ۴۰۰۰ کاراکتر باشد.');
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    api_respond(false, null, 'ایمیل معتبر نیست.');
}

$stmt = $pdo->prepare("SELECT avatar, card_image FROM admins WHERE id = ?");
$stmt->execute([$adminId]);
$current = $stmt->fetch();
$avatar = $current['avatar'] ?? null;
$cardImage = $current['card_image'] ?? null;

if ($removeAvatar && $avatar) {
    resa_delete_uploaded_image($avatar);
    $avatar = null;
}
if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
    $upload = resa_upload_image($_FILES['avatar'], 'admins');
    if (!$upload['ok']) {
        api_respond(false, null, $upload['message']);
    }
    if ($avatar) {
        resa_delete_uploaded_image($avatar);
    }
    $avatar = $upload['path'];
}

if ($removeCardImage && $cardImage) {
    resa_delete_uploaded_image($cardImage);
    $cardImage = null;
}
if (isset($_FILES['card_image']) && $_FILES['card_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $upload = resa_upload_image($_FILES['card_image'], 'admins/team-cards');
    if (!$upload['ok']) {
        api_respond(false, null, $upload['message']);
    }
    if ($cardImage) {
        resa_delete_uploaded_image($cardImage);
    }
    $cardImage = $upload['path'];
}

$stmt = $pdo->prepare("UPDATE admins SET
    display_name = ?, avatar = ?, job_title = ?, card_image = ?, bio_short = ?, bio_full = ?,
    social_telegram = ?, social_instagram = ?, social_whatsapp = ?, social_github = ?,
    social_email = ?, social_phone = ?, social_linkedin = ?, social_x = ?, social_youtube = ?, social_website = ?,
    social_pinterest = ?, social_custom_label = ?, social_custom_url = ?
    WHERE id = ?");
$stmt->execute([
    $displayName ?: null, $avatar, $jobTitle ?: null, $cardImage, $bioShort ?: null, $bioFull ?: null,
    $telegram ?: null, $instagram ?: null, $whatsapp ?: null, $github ?: null,
    $email ?: null, $phone ?: null, $linkedin ?: null, $x ?: null, $youtube ?: null, $website ?: null,
    $pinterest ?: null, ($customLabel && $customUrl) ? $customLabel : null, ($customLabel && $customUrl) ? $customUrl : null,
    $adminId,
]);

// همگام‌سازی نشست فعلی
$_SESSION['admin_display_name'] = $displayName ?: $_SESSION['admin_username'];
$_SESSION['admin_avatar'] = $avatar;

api_respond(true, ['avatar' => $avatar, 'card_image' => $cardImage], 'پروفایل با موفقیت ذخیره شد.');
