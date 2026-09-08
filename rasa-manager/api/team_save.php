<?php
/**
 * rasa-manager/api/team_save.php
 * ذخیره‌ی «کارت تیم ما». دو حالت با فیلد type:
 * - type=admin: سوپر ادمین کارت و مشخصات یکی از ادمین‌های موجود را ویرایش می‌کند (id = admins.id، اجباری)
 * - type=extra: افزودن/ویرایش عضوی که حساب ادمین ندارد (id اختیاری؛ خالی = ایجاد جدید)
 */
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();
resa_require_super_admin_api();

$type = (string)($_POST['type'] ?? 'extra');
$type = in_array($type, ['admin', 'extra'], true) ? $type : 'extra';

$jobTitle = trim((string)($_POST['job_title'] ?? ''));
$bioShort = trim((string)($_POST['bio_short'] ?? ''));
$isEnabled = (string)($_POST['is_enabled'] ?? '1') === '1' ? 1 : 0;
$removeCardImage = (string)($_POST['remove_card_image'] ?? '') === '1';
$customLabel = trim((string)($_POST['social_custom_label'] ?? ''));
$customUrl = trim((string)($_POST['social_custom_url'] ?? ''));
$customLabel = ($customLabel && $customUrl) ? $customLabel : null;
$customUrl = ($customLabel && $customUrl) ? $customUrl : null;

$socials = [];
foreach (array_keys(resa_social_platform_defs()) as $key) {
    $socials[$key] = trim((string)($_POST['social_' . $key] ?? ''));
}

if (mb_strlen($jobTitle) > 160) {
    api_respond(false, null, 'عنوان شغلی طولانی است.');
}
if (mb_strlen($bioShort) > 240) {
    api_respond(false, null, 'بیوگرافی خلاصه نباید بیشتر از ۲۴۰ کاراکتر باشد.');
}

$socialColNames = "social_telegram, social_instagram, social_whatsapp, social_github, social_linkedin, social_x, social_youtube, social_website, social_pinterest, social_custom_label, social_custom_url, social_email, social_phone";
$socialCols = "social_telegram = ?, social_instagram = ?, social_whatsapp = ?, social_github = ?, social_linkedin = ?, social_x = ?, social_youtube = ?, social_website = ?, social_pinterest = ?, social_custom_label = ?, social_custom_url = ?, social_email = ?, social_phone = ?";
$socialVals = [
    $socials['telegram'] ?: null, $socials['instagram'] ?: null, $socials['whatsapp'] ?: null, $socials['github'] ?: null,
    $socials['linkedin'] ?: null, $socials['x'] ?: null, $socials['youtube'] ?: null, $socials['website'] ?: null,
    $socials['pinterest'] ?: null, $customLabel, $customUrl,
    $socials['email'] ?: null, $socials['phone'] ?: null,
];

if ($type === 'admin') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        api_respond(false, null, 'شناسه ادمین نامعتبر است.');
    }
    $chk = $pdo->prepare("SELECT card_image, display_name, username FROM admins WHERE id = ?");
    $chk->execute([$id]);
    $existing = $chk->fetch();
    if (!$existing) {
        api_respond(false, null, 'ادمین یافت نشد.');
    }
    $cardImage = $existing['card_image'];
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

    $stmt = $pdo->prepare("UPDATE admins SET job_title = ?, card_image = ?, bio_short = ?, team_card_enabled = ?, $socialCols WHERE id = ?");
    $stmt->execute(array_merge(
        [$jobTitle ?: null, $cardImage, $bioShort ?: null, $isEnabled],
        $socialVals,
        [$id]
    ));

    resa_log_activity($pdo, resa_current_admin_id(), $_SESSION['admin_username'] ?? null, 'team_card_update', 'ویرایش کارت تیم: ' . ($existing['display_name'] ?: $existing['username']));
    api_respond(true, ['type' => 'admin', 'id' => $id, 'card_image' => $cardImage], 'کارت تیم بروزرسانی شد.');
} else {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : 0;
    $name = trim((string)($_POST['name'] ?? ''));
    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
        api_respond(false, null, 'نام باید بین ۲ تا ۱۲۰ کاراکتر باشد.');
    }

    $cardImage = null;
    if ($id > 0) {
        $chk = $pdo->prepare("SELECT card_image FROM team_extra_members WHERE id = ?");
        $chk->execute([$id]);
        $existing = $chk->fetch();
        if (!$existing) {
            api_respond(false, null, 'عضو تیم یافت نشد.');
        }
        $cardImage = $existing['card_image'];
        if ($removeCardImage && $cardImage) {
            resa_delete_uploaded_image($cardImage);
            $cardImage = null;
        }
    }
    if (isset($_FILES['card_image']) && $_FILES['card_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload = resa_upload_image($_FILES['card_image'], 'team');
        if (!$upload['ok']) {
            api_respond(false, null, $upload['message']);
        }
        if ($cardImage) {
            resa_delete_uploaded_image($cardImage);
        }
        $cardImage = $upload['path'];
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE team_extra_members SET name = ?, job_title = ?, card_image = ?, bio_short = ?, is_enabled = ?, $socialCols WHERE id = ?");
        $stmt->execute(array_merge(
            [$name, $jobTitle ?: null, $cardImage, $bioShort ?: null, $isEnabled],
            $socialVals,
            [$id]
        ));
        resa_log_activity($pdo, resa_current_admin_id(), $_SESSION['admin_username'] ?? null, 'team_extra_update', 'ویرایش عضو تیم: ' . $name);
        api_respond(true, ['type' => 'extra', 'id' => $id, 'card_image' => $cardImage], 'اطلاعات عضو تیم بروزرسانی شد.');
    } else {
        $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM team_extra_members")->fetchColumn();
        $stmt = $pdo->prepare("INSERT INTO team_extra_members
            (name, job_title, card_image, bio_short, is_enabled, sort_order, $socialColNames, created_at)
            VALUES (?,?,?,?,?,?, " . implode(',', array_fill(0, count($socialVals), '?')) . ", NOW())");
        $stmt->execute(array_merge(
            [$name, $jobTitle ?: null, $cardImage, $bioShort ?: null, $isEnabled, $maxOrder + 10],
            $socialVals
        ));
        resa_log_activity($pdo, resa_current_admin_id(), $_SESSION['admin_username'] ?? null, 'team_extra_add', 'افزودن عضو تیم: ' . $name);
        api_respond(true, ['type' => 'extra', 'id' => (int)$pdo->lastInsertId(), 'card_image' => $cardImage], 'عضو تیم اضافه شد.');
    }
}
