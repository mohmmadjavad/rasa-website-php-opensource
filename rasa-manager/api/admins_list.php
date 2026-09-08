<?php
require_once __DIR__ . '/_bootstrap.php';

// این اندپوینت دو مصرف‌کننده دارد: جدول «مدیریت ادمین‌ها» (نیازمند دسترسی
// manage_admins) و فهرست «نویسنده» در ویرایشگر وبلاگ (که باید برای همه‌ی
// ادمین‌های دارای دسترسی وبلاگ در دسترس باشد). برای مصرف‌کننده‌ی دوم فقط
// اطلاعات حداقلی و بدون نقش/دسترسی برگردانده می‌شود.
$canManage = resa_is_super_admin() || resa_can_manage_admins();

if ($canManage) {
    $stmt = $pdo->query("SELECT id, username, role, permissions, display_name, avatar, created_at FROM admins ORDER BY created_at ASC");
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['is_you'] = ((int)$r['id'] === resa_current_admin_id());
        $r['has_display_name'] = trim((string)$r['display_name']) !== '';
        $r['display_name'] = $r['display_name'] ?: $r['username'];
        $r['permissions'] = $r['role'] === 'super_admin' ? resa_full_permissions() : resa_normalize_permissions($r['permissions']);
    }
    unset($r);
} else {
    $stmt = $pdo->query("SELECT id, username, display_name, avatar FROM admins ORDER BY created_at ASC");
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['has_display_name'] = trim((string)$r['display_name']) !== '';
        $r['display_name'] = $r['display_name'] ?: $r['username'];
    }
    unset($r);
}

api_respond(true, ['admins' => $rows]);
