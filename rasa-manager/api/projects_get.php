<?php
require_once __DIR__ . '/_bootstrap.php';
resa_require_projects_api();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    api_respond(false, null, 'شناسه نامعتبر است.');
}

$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$id]);
$project = $stmt->fetch();
if (!$project) {
    api_respond(false, null, 'پروژه یافت نشد.');
}

$teamStmt = $pdo->prepare("SELECT admin_id FROM project_admins WHERE project_id = ? ORDER BY sort_order ASC");
$teamStmt->execute([$id]);
$adminIds = array_map('intval', array_column($teamStmt->fetchAll(), 'admin_id'));

if (!resa_can_access_project($adminIds)) {
    api_respond(false, null, 'شما اجازه دسترسی به این پروژه را ندارید.');
}

$catStmt = $pdo->prepare("SELECT category_id FROM project_categories WHERE project_id = ?");
$catStmt->execute([$id]);
$categoryIds = array_map('intval', array_column($catStmt->fetchAll(), 'category_id'));
if (!$categoryIds && $project['category_id']) {
    // سازگاری با پروژه‌های قدیمی که فقط category_id داشتند و هنوز رکوردی در project_categories ندارند
    $categoryIds = [(int)$project['category_id']];
}

$brandStmt = $pdo->prepare("SELECT brand_id FROM project_brands WHERE project_id = ?");
$brandStmt->execute([$id]);
$brandIds = array_map('intval', array_column($brandStmt->fetchAll(), 'brand_id'));

$memStmt = $pdo->prepare("SELECT id, name, role_title, avatar FROM project_members WHERE project_id = ? ORDER BY sort_order ASC, id ASC");
$memStmt->execute([$id]);
$members = $memStmt->fetchAll();
foreach ($members as &$m) {
    $m['id'] = (int)$m['id'];
}
unset($m);

$project['id'] = (int)$project['id'];
$project['category_id'] = $project['category_id'] !== null ? (int)$project['category_id'] : null;
$project['category_ids'] = $categoryIds;
$project['brand_ids'] = $brandIds;
$project['views'] = (int)$project['views'];
$project['admin_ids'] = $adminIds;
$project['members'] = $members;

api_respond(true, ['project' => $project]);
