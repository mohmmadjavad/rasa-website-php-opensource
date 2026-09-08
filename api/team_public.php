<?php
/**
 * api/team_public.php
 * فهرست عمومی «کارت‌های تیم ما» برای صفحه اصلی و درباره ما (هر دو از همین اندپوینت می‌خوانند
 * تا کارت‌ها دقیقاً یکسان باشند). فقط کارت‌های فعال را برمی‌گرداند.
 * توجه: image = عکس کارت تیم (برای پس‌زمینه‌ی کارت)، avatar = عکس پروفایل (برای مودال).
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
    social_telegram, social_instagram, social_whatsapp, social_github, social_linkedin, social_x, social_youtube, social_website, social_pinterest, social_custom_label, social_custom_url, social_email, social_phone,
    team_card_sort_order
    FROM admins WHERE team_card_enabled = 1 ORDER BY team_card_sort_order, id")->fetchAll();

$extras = $pdo->query("SELECT id, name, card_image, job_title, bio_short,
    social_telegram, social_instagram, social_whatsapp, social_github, social_linkedin, social_x, social_youtube, social_website, social_pinterest, social_custom_label, social_custom_url, social_email, social_phone,
    sort_order
    FROM team_extra_members WHERE is_enabled = 1 ORDER BY sort_order, id")->fetchAll();

$items = [];
foreach ($admins as $a) {
    $items[] = [
        'name'        => $a['display_name'] ?: $a['username'],
        'job_title'   => $a['job_title'],
        'card_image'  => $a['card_image'] ?: $a['avatar'],
        'avatar'      => $a['avatar'] ?: $a['card_image'],
        'bio_short'   => $a['bio_short'],
        'socials'     => resa_social_list_from_row($a),
        'profile_url' => 'resume.html?user=' . rawurlencode($a['username']),
        'sort_order'  => (int)$a['team_card_sort_order'],
    ];
}
foreach ($extras as $e) {
    $items[] = [
        'name'        => $e['name'],
        'job_title'   => $e['job_title'],
        'card_image'  => $e['card_image'],
        'avatar'      => $e['card_image'],
        'bio_short'   => $e['bio_short'],
        'socials'     => resa_social_list_from_row($e),
        'profile_url' => null,
        'sort_order'  => (int)$e['sort_order'],
    ];
}

usort($items, function ($a, $b) { return $a['sort_order'] <=> $b['sort_order']; });

respond(true, ['team' => $items]);
