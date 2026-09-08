<?php
require_once __DIR__ . '/_bootstrap.php';
resa_require_manage_site_api();

$autoApprove = resa_get_setting($pdo, 'comments_auto_approve', '1') === '1';
$maintenanceMode = resa_get_setting($pdo, 'maintenance_mode', '0') === '1';
$maintenanceMessage = (string)resa_get_setting($pdo, 'maintenance_message', '');

api_respond(true, [
    'comments_auto_approve'  => $autoApprove,
    'maintenance_mode'       => $maintenanceMode,
    'maintenance_message'    => $maintenanceMessage,
]);
