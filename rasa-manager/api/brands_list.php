<?php
require_once __DIR__ . '/_bootstrap.php';
resa_require_projects_api();

$stmt = $pdo->query("
    SELECT b.id, b.name, b.slug, b.logo_image,
           (SELECT COUNT(*) FROM project_brands pb WHERE pb.brand_id = b.id) AS project_count
    FROM brands b
    ORDER BY b.name ASC
");
$rows = $stmt->fetchAll();
foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['project_count'] = (int)$r['project_count'];
}
unset($r);

api_respond(true, ['brands' => $rows]);
