<?php
/**
 * api/projects_list.php
 * فهرست عمومی پروژه‌های منتشرشده — با فیلتر دسته‌بندی، جستجو و صفحه‌بندی.
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

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(1, min(60, (int)($_GET['per_page'] ?? 9)));
$offset = ($page - 1) * $perPage;

$q = trim((string)($_GET['q'] ?? ''));
$categorySlug = trim((string)($_GET['category'] ?? ''));
$brandSlug = trim((string)($_GET['brand'] ?? ''));
$authorId = (int)($_GET['author'] ?? 0);

$where = ["p.status = 'published'", "(p.published_at IS NULL OR p.published_at <= NOW())"];
$params = [];

if ($authorId > 0) {
    // صفحه پروفایل: همه‌ی پروژه‌هایی که آن عضو در آن‌ها مشارکت داشته یا خودش ساخته،
    // صرف‌نظر از display_scope (که فقط ویترین عمومی/صفحه پروژه‌ها را کنترل می‌کند).
    $where[] = "p.id IN (SELECT project_id FROM project_admins WHERE admin_id = ? UNION SELECT id FROM projects WHERE created_by_admin_id = ?)";
    $params[] = $authorId;
    $params[] = $authorId;
} else {
    $where[] = "p.display_scope != 'profile_only'";
}

if ($q !== '') {
    $where[] = "(p.title LIKE ? OR p.excerpt LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like;
}

$categoryJoin = '';
if ($categorySlug !== '') {
    $categoryJoin = "JOIN categories fc ON fc.id = p.category_id";
    $where[] = "fc.slug = ?";
    $params[] = $categorySlug;
}

$activeBrand = null;
if ($brandSlug !== '') {
    $brandStmt = $pdo->prepare("SELECT id, name, slug FROM brands WHERE slug = ?");
    $brandStmt->execute([$brandSlug]);
    $activeBrand = $brandStmt->fetch() ?: null;
    if ($activeBrand) {
        $where[] = "p.id IN (SELECT project_id FROM project_brands WHERE brand_id = ?)";
        $params[] = (int)$activeBrand['id'];
    } else {
        // برند نامعتبر → هیچ نتیجه‌ای برنگردد
        $where[] = "1 = 0";
    }
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(DISTINCT p.id) FROM projects p $categoryJoin $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT p.id, p.title, p.slug, p.excerpt, p.cover_type, p.cover_image, p.cover_video,
           p.published_at, p.views, c.name AS category_name, c.slug AS category_slug
    FROM projects p
    LEFT JOIN categories c ON c.id = p.category_id
    $categoryJoin
    $whereSql
    ORDER BY p.published_at DESC, p.id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

if ($rows) {
    $ids = array_column($rows, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $teamStmt = $pdo->prepare("
        SELECT pa.project_id, pa.admin_id, a.username, COALESCE(NULLIF(a.display_name,''), a.username) AS name, a.avatar
        FROM project_admins pa JOIN admins a ON a.id = pa.admin_id
        WHERE pa.project_id IN ($in) ORDER BY pa.sort_order ASC
    ");
    $teamStmt->execute($ids);
    $teamByProject = [];
    foreach ($teamStmt->fetchAll() as $tr) {
        $teamByProject[$tr['project_id']][] = ['admin_id' => (int)$tr['admin_id'], 'username' => $tr['username'], 'name' => $tr['name'], 'avatar' => $tr['avatar']];
    }

    $brandStmt2 = $pdo->prepare("
        SELECT pb.project_id, b.slug, b.name
        FROM project_brands pb JOIN brands b ON b.id = pb.brand_id
        WHERE pb.project_id IN ($in) ORDER BY b.name ASC
    ");
    $brandStmt2->execute($ids);
    $brandsByProject = [];
    foreach ($brandStmt2->fetchAll() as $br) {
        $brandsByProject[$br['project_id']][] = ['slug' => $br['slug'], 'name' => $br['name']];
    }
} else {
    $teamByProject = [];
    $brandsByProject = [];
}

foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['views'] = (int)$r['views'];
    $r['team'] = $teamByProject[$r['id']] ?? [];
    $r['brands'] = $brandsByProject[$r['id']] ?? [];
}
unset($r);

$catStmt = $pdo->query("SELECT id, name, slug FROM categories WHERE type = 'project' ORDER BY name ASC");
$categories = $catStmt->fetchAll();
foreach ($categories as &$c) { $c['id'] = (int)$c['id']; }
unset($c);

respond(true, [
    'projects' => $rows,
    'categories' => $categories,
    'active_brand' => $activeBrand ? ['id' => (int)$activeBrand['id'], 'name' => $activeBrand['name'], 'slug' => $activeBrand['slug']] : null,
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
    'total_pages' => (int)ceil($total / $perPage),
]);
