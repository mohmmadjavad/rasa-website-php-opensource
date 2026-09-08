<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();
resa_require_projects_api();

$body = api_json_body();
$id = (int)($body['id'] ?? 0);
if ($id <= 0) {
    api_respond(false, null, 'شناسه نامعتبر است.');
}

$stmt = $pdo->prepare("SELECT title, cover_image, cover_video, cover_type FROM projects WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    api_respond(false, null, 'پروژه یافت نشد.');
}

$teamStmt = $pdo->prepare("SELECT admin_id FROM project_admins WHERE project_id = ?");
$teamStmt->execute([$id]);
$adminIds = array_map('intval', array_column($teamStmt->fetchAll(), 'admin_id'));
if (!resa_can_access_project($adminIds)) {
    api_respond(false, null, 'شما اجازه حذف این پروژه را ندارید.');
}

$memStmt = $pdo->prepare("SELECT avatar FROM project_members WHERE project_id = ?");
$memStmt->execute([$id]);
$memberAvatars = array_column($memStmt->fetchAll(), 'avatar');

$pdo->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);

if (!empty($row['cover_image'])) {
    resa_delete_uploaded_image($row['cover_image']);
}
if (!empty($row['cover_video']) && strpos($row['cover_video'], 'assets/uploads/') === 0) {
    resa_delete_uploaded_image($row['cover_video']);
}
foreach ($memberAvatars as $av) {
    resa_delete_uploaded_image($av);
}

resa_log_activity(
    $pdo,
    resa_current_admin_id(),
    $_SESSION['admin_username'] ?? null,
    'project_delete',
    'حذف پروژه «' . $row['title'] . '» (#' . $id . ').'
);

api_respond(true, null, 'پروژه حذف شد.');
