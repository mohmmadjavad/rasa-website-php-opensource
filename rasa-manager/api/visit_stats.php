<?php
/**
 * admin/api/visit_stats.php
 * آمار خلاصه بازدید صفحات سایت (فقط سوپر ادمین).
 */
require_once __DIR__ . '/_bootstrap.php';
resa_require_view_stats_api();

$stats = resa_get_visit_stats($pdo);

api_respond(true, $stats);
