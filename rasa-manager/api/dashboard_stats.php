<?php
/**
 * admin/api/dashboard_stats.php
 * آمار خلاصه برای تب «داشبورد» پنل ادمین: تعداد وبلاگ‌ها/پروژه‌ها،
 * نظرات در انتظار، پیام‌های نخوانده، پربازدیدترین محتوا، و (فقط برای
 * سوپر ادمین) آمار بازدید کلی سایت. هر بخش بر اساس دسترسی/محدوده‌ی
 * همان ادمین فیلتر می‌شود، دقیقاً مثل بقیه‌ی لیست‌های پنل.
 */
require_once __DIR__ . '/_bootstrap.php';
resa_require_dashboard_api();

$currentAdminId = resa_current_admin_id();
$isSuperAdmin = resa_is_super_admin();

$out = [
    'can_view_articles' => resa_can_access_blog(),
    'can_view_projects' => resa_can_access_projects(),
    'can_view_comments' => resa_can_access_comments(),
    'can_view_messages' => resa_can_access_messages(),
    'can_view_visits'   => $isSuperAdmin,
];

/* ---------- وبلاگ‌ها ---------- */
if ($out['can_view_articles']) {
    $ownOnly = resa_blog_scope() !== 'all' && !$isSuperAdmin;
    $where = $ownOnly ? 'WHERE author_admin_id = ?' : '';
    $params = $ownOnly ? [$currentAdminId] : [];

    $stmt = $pdo->prepare("SELECT status, COUNT(*) AS c FROM articles $where GROUP BY status");
    $stmt->execute($params);
    $counts = ['published' => 0, 'draft' => 0];
    foreach ($stmt->fetchAll() as $row) $counts[$row['status']] = (int)$row['c'];

    $topStmt = $pdo->prepare("SELECT id, title, slug, views FROM articles $where ORDER BY views DESC LIMIT 5");
    $topStmt->execute($params);

    $out['articles'] = [
        'total'     => $counts['published'] + $counts['draft'],
        'published' => $counts['published'],
        'draft'     => $counts['draft'],
        'top'       => $topStmt->fetchAll(),
    ];
}

/* ---------- پروژه‌ها ---------- */
if ($out['can_view_projects']) {
    $ownOnly = resa_projects_scope() !== 'all' && !$isSuperAdmin;
    $where = $ownOnly ? 'WHERE p.created_by_admin_id = ? OR EXISTS (SELECT 1 FROM project_admins pa WHERE pa.project_id = p.id AND pa.admin_id = ?)' : '';
    $params = $ownOnly ? [$currentAdminId, $currentAdminId] : [];

    $stmt = $pdo->prepare("SELECT p.status, COUNT(*) AS c FROM projects p $where GROUP BY p.status");
    $stmt->execute($params);
    $counts = ['published' => 0, 'draft' => 0];
    foreach ($stmt->fetchAll() as $row) $counts[$row['status']] = (int)$row['c'];

    $topStmt = $pdo->prepare("SELECT p.id, p.title, p.slug, p.views FROM projects p $where ORDER BY p.views DESC LIMIT 5");
    $topStmt->execute($params);

    $out['projects'] = [
        'total'     => $counts['published'] + $counts['draft'],
        'published' => $counts['published'],
        'draft'     => $counts['draft'],
        'top'       => $topStmt->fetchAll(),
    ];
}

/* ---------- نظرات ---------- */
if ($out['can_view_comments']) {
    $ownOnly = resa_comments_scope() !== 'all' && !$isSuperAdmin;
    $join = $ownOnly ? 'JOIN articles a ON a.id = c.article_id AND a.author_admin_id = ?' : '';
    $params = $ownOnly ? [$currentAdminId] : [];

    $stmt = $pdo->prepare("SELECT c.status, COUNT(*) AS cnt FROM comments c $join GROUP BY c.status");
    $stmt->execute($params);
    $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
    foreach ($stmt->fetchAll() as $row) $counts[$row['status']] = (int)$row['cnt'];

    $spamParams = $ownOnly ? [$currentAdminId] : [];
    $spamStmt = $pdo->prepare("SELECT COUNT(*) FROM comments c $join WHERE c.spam_reason IS NOT NULL AND c.status = 'pending'");
    $spamStmt->execute($spamParams);

    $out['comments'] = [
        'pending'  => $counts['pending'],
        'approved' => $counts['approved'],
        'rejected' => $counts['rejected'],
        'spam_suspected' => (int)$spamStmt->fetchColumn(),
    ];
}

/* ---------- پیام‌های تماس با ما ---------- */
if ($out['can_view_messages']) {
    $out['messages'] = [
        'unread' => (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE is_read = 0")->fetchColumn(),
        'total'  => (int)$pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn(),
    ];
}

/* ---------- بازدید سایت (فقط سوپر ادمین) ---------- */
if ($out['can_view_visits']) {
    $out['visits'] = resa_get_visit_stats($pdo);
}

/* ---------- تعداد ادمین‌ها (فقط سوپر ادمین) ---------- */
if ($isSuperAdmin) {
    $out['admins_count'] = (int)$pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
}

api_respond(true, $out);
