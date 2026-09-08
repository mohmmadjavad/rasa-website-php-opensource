<?php
/**
 * rasa-manager/api/team_list.php
 * فهرست یکپارچه‌ی «کارت‌های تیم ما»: هم ادمین‌هایی که برای خودشان کارت تعریف کرده‌اند
 * و هم اعضایی که سوپر ادمین بدون حساب کاربری اضافه کرده. فقط برای سوپر ادمین.
 */
require_once __DIR__ . '/_bootstrap.php';
resa_require_super_admin_api();

$admins = $pdo->query("SELECT id, username, role, display_name, avatar, job_title, card_image, bio_short,
    social_telegram, social_instagram, social_whatsapp, social_github, social_linkedin, social_x, social_youtube, social_website, social_pinterest, social_custom_label, social_custom_url, social_email, social_phone,
    team_card_enabled, team_card_sort_order
    FROM admins ORDER BY team_card_sort_order, id")->fetchAll();

$extras = $pdo->query("SELECT id, name, job_title, card_image, bio_short,
    social_telegram, social_instagram, social_whatsapp, social_github, social_linkedin, social_x, social_youtube, social_website, social_pinterest, social_custom_label, social_custom_url, social_email, social_phone,
    is_enabled, sort_order
    FROM team_extra_members ORDER BY sort_order, id")->fetchAll();

$items = [];
foreach ($admins as $a) {
    $items[] = [
        'type'          => 'admin',
        'id'            => (int)$a['id'],
        'name'          => $a['display_name'] ?: $a['username'],
        'username'      => $a['username'],
        'role'          => $a['role'],
        'job_title'     => $a['job_title'],
        'card_image'    => $a['card_image'],
        'avatar'        => $a['avatar'],
        'bio_short'     => $a['bio_short'],
        'socials'       => resa_social_list_from_row($a),
        'is_enabled'    => (bool)$a['team_card_enabled'],
        'sort_order'    => (int)$a['team_card_sort_order'],
        'has_profile'   => true,
    ];
}
foreach ($extras as $e) {
    $items[] = [
        'type'          => 'extra',
        'id'            => (int)$e['id'],
        'name'          => $e['name'],
        'username'      => null,
        'role'          => null,
        'job_title'     => $e['job_title'],
        'card_image'    => $e['card_image'],
        'avatar'        => null,
        'bio_short'     => $e['bio_short'],
        'socials'       => resa_social_list_from_row($e),
        'is_enabled'    => (bool)$e['is_enabled'],
        'sort_order'    => (int)$e['sort_order'],
        'has_profile'   => false,
    ];
}

usort($items, function ($a, $b) {
    return $a['sort_order'] <=> $b['sort_order'] ?: $a['id'] <=> $b['id'];
});

api_respond(true, ['items' => $items, 'social_platforms' => resa_social_platform_defs()]);
