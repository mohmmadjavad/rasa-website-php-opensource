<?php
require_once __DIR__ . '/_bootstrap.php';
resa_require_super_admin_api();

$rows = $pdo->query("SELECT id, image, title, sort_order FROM awards ORDER BY sort_order, id")->fetchAll();
foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['sort_order'] = (int)$r['sort_order'];
}
unset($r);

api_respond(true, ['awards' => $rows]);
