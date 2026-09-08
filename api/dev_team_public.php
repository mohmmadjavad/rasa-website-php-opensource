<?php
/**
 * api/dev_team_public.php
 * فهرست عمومی «افراد توسعه دهنده» برای صفحه تماس با ما — فقط ادمین‌هایی که سوپر ادمین
 * از تنظیمات پنل، فعال و مرتب کرده باشد (dev_team_enabled). خوانده می‌شود توسط js/dev-team-section.js.
 */
define('RESA_APP', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/team-helpers.php';

header('Content-Type: application/json; charset=utf-8');

function respond(bool $ok, $data = null, string $message = ''): void
{
    $out = ['ok' => $ok];
    if ($message !== '') $out['message'] = $message;
    if ($data !== null) $out['data'] = $data;
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = resa_db();

$admins = $pdo->query("SELECT id, display_name, username, avatar, job_title, card_image, bio_short,
    social_telegram, social_instagram, social_whatsapp, social_github, social_linkedin, social_x, social_youtube, social_website, social_pinterest, social_custom_label, social_custom_url, social_email, social_phone
    FROM admins WHERE dev_team_enabled = 1 ORDER BY dev_team_sort_order, id")->fetchAll();

$items = [];
foreach ($admins as $a) {
    $items[] = [
        'name'        => $a['display_name'] ?: $a['username'],
        'job_title'   => $a['job_title'],
        'bio_short'   => $a['bio_short'],
        'avatar'      => $a['avatar'] ?: $a['card_image'],
        'card_image'  => $a['card_image'] ?: $a['avatar'],
        'socials'     => resa_social_list_from_row($a),
        'profile_url' => 'resume.html?user=' . rawurlencode($a['username']),
    ];
}

respond(true, ['developers' => $items]);
