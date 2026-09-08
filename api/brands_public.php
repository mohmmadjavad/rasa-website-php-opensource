<?php
/**
 * api/brands_public.php
 * فهرست عمومی برندهایی که رسا با آن‌ها کار کرده — فقط برندهایی که حداقل
 * یک پروژه‌ی منتشرشده و قابل‌نمایش در «صفحه پروژه‌ها» دارند.
 */

define('RESA_APP', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

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

$stmt = $pdo->query("
    SELECT b.id, b.name, b.slug, b.logo_image, COUNT(DISTINCT p.id) AS project_count
    FROM brands b
    JOIN project_brands pb ON pb.brand_id = b.id
    JOIN projects p ON p.id = pb.project_id
        AND p.status = 'published'
        AND (p.published_at IS NULL OR p.published_at <= NOW())
        AND p.display_scope != 'profile_only'
    GROUP BY b.id, b.name, b.slug, b.logo_image
    HAVING project_count > 0
    ORDER BY b.name ASC
");
$brands = $stmt->fetchAll();
foreach ($brands as &$b) {
    $b['id'] = (int)$b['id'];
    $b['project_count'] = (int)$b['project_count'];
}
unset($b);

respond(true, ['brands' => $brands]);
