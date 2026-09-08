<?php
/**
 * admin/api/activity_log_list.php
 * فهرست آخرین رویدادهای امنیتی/مدیریتی پنل ادمین (فقط سوپر ادمین).
 */
require_once __DIR__ . '/_bootstrap.php';
resa_require_view_stats_api();

$stmt = $pdo->prepare(
    "SELECT id, admin_username, action, description, ip_address, created_at
     FROM activity_logs
     ORDER BY id DESC
     LIMIT 80"
);
$stmt->execute();
$logs = $stmt->fetchAll();

api_respond(true, ['logs' => $logs]);
