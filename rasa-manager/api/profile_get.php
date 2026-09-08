<?php
require_once __DIR__ . '/_bootstrap.php';

$stmt = $pdo->prepare("SELECT id, username, role, display_name, avatar, job_title, card_image, bio_short, bio_full,
    social_telegram, social_instagram, social_whatsapp, social_github, social_email, social_phone, social_linkedin, social_x, social_youtube, social_website, social_pinterest, social_custom_label, social_custom_url
    FROM admins WHERE id = ?");
$stmt->execute([resa_current_admin_id()]);
$admin = $stmt->fetch();

if (!$admin) {
    api_respond(false, null, 'پروفایل یافت نشد.');
}

$admin['id'] = (int)$admin['id'];

api_respond(true, ['admin' => $admin]);
