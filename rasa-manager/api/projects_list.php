<?php
require_once __DIR__ . '/_bootstrap.php';
resa_require_projects_api();

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : 0;

$canSeeAll = resa_is_super_admin() || resa_projects_scope() === 'all';
$currentAdminId = resa_current_admin_id();

$where = [];
$params = [];

if (!$canSeeAll) {
    $where[] = "p.id IN (SELECT project_id FROM project_admins WHERE admin_id = ?)";
    $params[] = $currentAdminId;
}
if ($q !== '') {
    $where[] = "p.title LIKE ?";
    $params[] = '%' . $q . '%';
}
if ($status === 'draft' || $status === 'published') {
    $where[] = "p.status = ?";
    $params[] = $status;
}
if ($categoryId > 0) {
    $where[] = "(p.id IN (SELECT project_id FROM project_categories WHERE category_id = ?) OR p.category_id = ?)";
    $params[] = $categoryId;
    $params[] = $categoryId;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("
    SELECT p.id, p.title, p.slug, p.status, p.cover_type, p.cover_image, p.cover_video,
           p.views, p.published_at, p.created_at, p.updated_at,
           c.id AS category_id, c.name AS category_name
    FROM projects p
    LEFT JOIN categories c ON c.id = p.category_id
    $whereSql
    ORDER BY p.created_at DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

if ($rows) {
    $ids = array_column($rows, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $teamStmt = $pdo->prepare("
        SELECT pa.project_id, COALESCE(NULLIF(a.display_name,''), a.username) AS name, a.avatar
        FROM project_admins pa JOIN admins a ON a.id = pa.admin_id
        WHERE pa.project_id IN ($in) ORDER BY pa.sort_order ASC
    ");
    $teamStmt->execute($ids);
    $teamByProject = [];
    foreach ($teamStmt->fetchAll() as $tr) {
        $teamByProject[$tr['project_id']][] = ['name' => $tr['name'], 'avatar' => $tr['avatar']];
    }

    $catStmt = $pdo->prepare("
        SELECT pc.project_id, c.id AS category_id, c.name AS category_name
        FROM project_categories pc JOIN categories c ON c.id = pc.category_id
        WHERE pc.project_id IN ($in)
    ");
    $catStmt->execute($ids);
    $catsByProject = [];
    foreach ($catStmt->fetchAll() as $cr) {
        $catsByProject[$cr['project_id']][] = ['id' => (int)$cr['category_id'], 'name' => $cr['category_name']];
    }
} else {
    $teamByProject = [];
    $catsByProject = [];
}

$scopeSql = $canSeeAll ? '' : 'WHERE id IN (SELECT project_id FROM project_admins WHERE admin_id = ' . (int)$currentAdminId . ')';
$counts = $pdo->query("SELECT status, COUNT(*) c FROM projects $scopeSql GROUP BY status")->fetchAll();
$statCounts = ['draft' => 0, 'published' => 0];
foreach ($counts as $c) {
    $statCounts[$c['status']] = (int)$c['c'];
}

foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['category_id'] = $r['category_id'] !== null ? (int)$r['category_id'] : null;
    $r['views'] = (int)$r['views'];
    $r['team'] = $teamByProject[$r['id']] ?? [];
    $r['categories'] = $catsByProject[$r['id']] ?? ($r['category_id'] ? [['id' => $r['category_id'], 'name' => $r['category_name']]] : []);
}
unset($r);

api_respond(true, [
    'projects' => $rows,
    'total_count' => $statCounts['draft'] + $statCounts['published'],
    'published_count' => $statCounts['published'],
    'draft_count' => $statCounts['draft'],
]);
