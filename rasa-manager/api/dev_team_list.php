<?php
/**
 * rasa-manager/api/dev_team_list.php
 * فهرست همه‌ی ادمین‌ها برای بخش تنظیمات «افراد توسعه دهنده» (فقط سوپر ادمین):
 * مشخص می‌کند کدام ادمین‌ها در بخش «افراد توسعه دهنده» صفحه تماس با ما نمایش داده شوند و با چه ترتیبی.
 */
require_once __DIR__ . '/_bootstrap.php';
resa_require_super_admin_api();

$stmt = $pdo->query(
    "SELECT id, username, display_name, avatar, card_image, job_title, dev_team_enabled, dev_team_sort_order
     FROM admins
     ORDER BY dev_team_enabled DESC, dev_team_sort_order ASC, id ASC"
);
$rows = $stmt->fetchAll();
foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['display_name'] = $r['display_name'] ?: $r['username'];
    $r['dev_team_enabled'] = (int)$r['dev_team_enabled'];
    $r['dev_team_sort_order'] = (int)$r['dev_team_sort_order'];
}
unset($r);

api_respond(true, ['admins' => $rows]);
