<?php
/**
 * admin/index.php
 * داشبورد اصلی پنل ادمین — ۴ تب: پیام‌ها، پروژه‌ها، وبلاگ‌ها، تنظیمات.
 */

define('RESA_APP', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/backup-helpers.php';
require_once __DIR__ . '/../includes/cpanel-api.php';
require_once __DIR__ . '/../includes/mail-crypto.php';
require_once __DIR__ . '/../includes/mail-helpers.php';
require_once __DIR__ . '/../includes/webmail-sso.php';

resa_require_login();
$pdo = resa_db();
resa_sync_admin_session($pdo);

// بک‌آپ خودکار «فرصت‌طلبانه»: چون هاست کنترل‌پنلی/لاراگون معمولاً Cron واقعی
// ندارد، هر بار پنل ادمین باز می‌شود این تابع چک می‌کند که آیا زمان بک‌آپ
// خودکار رسیده یا نه — هزینه‌اش فقط یک مقایسه‌ی زمان است مگر واقعاً لازم باشد.
resa_run_scheduled_backup_if_due($pdo);

$stmt = $pdo->query("SELECT COUNT(*) FROM messages WHERE is_read = 0");
$unreadCount = (int)$stmt->fetchColumn();

$csrf = resa_csrf_token();
$currentAdmin = htmlspecialchars($_SESSION['admin_username'], ENT_QUOTES, 'UTF-8');
$currentRole = resa_current_admin_role();
$isSuperAdmin = resa_is_super_admin();
$canSeeMessages = resa_is_super_admin() || resa_can_access_messages();
$canDeleteMessages = resa_is_super_admin() || resa_can_delete_messages();
$canSeeProjects = resa_is_super_admin() || resa_can_access_projects();
$projectsScope = resa_is_super_admin() ? 'all' : resa_projects_scope();
$canManageProjectCategories = resa_is_super_admin() || resa_can_manage_project_categories();
$canSeeBlog = resa_is_super_admin() || resa_can_access_blog();
$blogScope = resa_is_super_admin() ? 'all' : resa_blog_scope();
$canManageCategories = resa_is_super_admin() || resa_can_manage_categories();
$canSeeComments = resa_is_super_admin() || resa_can_access_comments();
$commentsScope = resa_is_super_admin() ? 'all' : resa_comments_scope();
$canManageAdmins = resa_is_super_admin() || resa_can_manage_admins();
$canSeeDashboard = resa_is_super_admin() || resa_can_access_dashboard();
$canManageSite = resa_is_super_admin() || resa_can_manage_site();
$canViewStats = resa_is_super_admin() || resa_can_view_stats();
$canManageBackup = resa_is_super_admin() || resa_can_manage_backup();
$canViewHealth = resa_is_super_admin() || resa_can_view_health();

$defaultTab = $canSeeDashboard ? 'dashboard' : ($canSeeMessages ? 'messages' : ($canSeeProjects ? 'projects' : ($canSeeBlog ? 'articles' : ($canSeeComments ? 'comments' : 'settings'))));
$tabTitles = [
    'dashboard' => 'داشبورد',
    'messages' => 'پیام‌های تماس با ما',
    'projects' => 'مدیریت پروژه‌ها',
    'articles' => 'مدیریت وبلاگ‌ها',
    'comments' => 'نظرات کاربران',
    'email' => 'ایمیل',
    'settings' => 'تنظیمات',
];

/**
 * پنل چک‌باکس‌های دسترسی سفارشی — هم در فرم «افزودن ادمین» و هم در مودال
 * «ویرایش ادمین» استفاده می‌شود.
 */
require_once __DIR__ . '/includes/permission-fields.php';

/**
 * نوار ابزار ویرایشگر متنی غنی (RTE) — مشترک بین ویرایشگر وبلاگ و پروژه
 * تا هر دو همیشه امکانات یکسان و هماهنگ داشته باشند.
 */
require_once __DIR__ . '/includes/rte-toolbar.php';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>پنل ادمین | رسا</title>
<link rel="icon" href="../assets/logo/logo-filled-cyan.svg" type="image/svg+xml">
<link rel="apple-touch-icon" sizes="180x180" href="../assets/logo/apple-touch-icon-admin.png">
<link rel="manifest" href="manifest.webmanifest">
<meta name="theme-color" content="#171a1a">
<link href="../assets/vendor/vazirmatn/Vazirmatn-font-face.css" rel="stylesheet">
<link rel="stylesheet" href="assets/admin.css">
<link rel="stylesheet" href="assets/jalali-datepicker.css">
</head>
<body class="admin-body admin-role-<?= htmlspecialchars($currentRole, ENT_QUOTES, 'UTF-8') ?>">

<meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
<meta name="admin-role" content="<?= htmlspecialchars($currentRole, ENT_QUOTES, 'UTF-8') ?>">
<meta name="admin-id" content="<?= (int)$_SESSION['admin_id'] ?>">
<meta name="blog-scope" content="<?= htmlspecialchars($blogScope, ENT_QUOTES, 'UTF-8') ?>">
<meta name="projects-scope" content="<?= htmlspecialchars($projectsScope, ENT_QUOTES, 'UTF-8') ?>">
<meta name="can-manage-categories" content="<?= $canManageCategories ? '1' : '0' ?>">
<meta name="can-manage-project-categories" content="<?= $canManageProjectCategories ? '1' : '0' ?>">
<meta name="can-delete-messages" content="<?= $canDeleteMessages ? '1' : '0' ?>">
<meta name="comments-scope" content="<?= htmlspecialchars($commentsScope, ENT_QUOTES, 'UTF-8') ?>">

<div class="admin-shell">

  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <aside class="admin-sidebar">
    <div class="sidebar-brand">
      <img src="../assets/logo/logo-filled-cyan.svg" alt="رسا">
      <span>رسا</span>
    </div>

    <nav class="sidebar-nav">
      <button class="side-tab <?= $defaultTab === 'email' ? 'is-active' : '' ?>" data-tab="email" type="button">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5.5" width="17" height="13" rx="1.6"/><path d="M4.5 7 12 13l7.5-6"/></svg>
        <span>ایمیل</span>
      </button>
      <?php if ($canSeeDashboard): ?>
      <button class="side-tab <?= $defaultTab === 'dashboard' ? 'is-active' : '' ?>" data-tab="dashboard" type="button">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="3.5" width="7.5" height="7.5" rx="1.4"/><rect x="13" y="3.5" width="7.5" height="5" rx="1.4"/><rect x="13" y="10.5" width="7.5" height="10" rx="1.4"/><rect x="3.5" y="13" width="7.5" height="7.5" rx="1.4"/></svg>
        <span>داشبورد</span>
      </button>
      <?php endif; ?>
      <?php if ($canSeeMessages): ?>
      <button class="side-tab <?= $defaultTab === 'messages' ? 'is-active' : '' ?>" data-tab="messages" type="button">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5.5" width="17" height="13" rx="1.6"/><path d="M4.5 7 12 13l7.5-6"/></svg>
        <span>پیام‌ها</span>
        <span class="badge-unread" id="sidebarUnreadBadge" <?= $unreadCount ? '' : 'style="display:none;"' ?>><?= $unreadCount ?></span>
      </button>
      <?php endif; ?>
      <?php if ($canSeeProjects): ?>
      <button class="side-tab <?= $defaultTab === 'projects' ? 'is-active' : '' ?>" data-tab="projects" type="button">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5.5" width="7" height="7" rx="1.2"/><rect x="13.5" y="5.5" width="7" height="7" rx="1.2"/><rect x="3.5" y="15.5" width="7" height="7" rx="1.2"/><rect x="13.5" y="15.5" width="7" height="7" rx="1.2"/></svg>
        <span>پروژه‌ها</span>
      </button>
      <?php endif; ?>
      <?php if ($canSeeBlog): ?>
      <button class="side-tab <?= $defaultTab === 'articles' ? 'is-active' : '' ?>" data-tab="articles" type="button">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3.5h9L19 8v12.5a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1Z"/><path d="M9 12h6M9 16h6"/></svg>
        <span>وبلاگ‌ها</span>
      </button>
      <?php endif; ?>
      <?php if ($canSeeComments): ?>
      <button class="side-tab <?= $defaultTab === 'comments' ? 'is-active' : '' ?>" data-tab="comments" type="button">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4.5h16v12H8.5L4 20.5v-4H4z"/><path d="M8 9.5h8M8 13h5"/></svg>
        <span>نظرات کاربران</span>
        <span class="badge-unread" id="sidebarCommentsBadge" style="display:none;"></span>
      </button>
      <?php endif; ?>
      <button class="side-tab <?= $defaultTab === 'settings' ? 'is-active' : '' ?>" data-tab="settings" type="button">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3.2"/><path d="M19.4 13a7.7 7.7 0 0 0 0-2l2-1.5-2-3.4-2.3.9a7.6 7.6 0 0 0-1.7-1L15 3.5h-4l-.4 2.5a7.6 7.6 0 0 0-1.7 1l-2.3-.9-2 3.4L6.6 11a7.7 7.7 0 0 0 0 2l-2 1.5 2 3.4 2.3-.9a7.6 7.6 0 0 0 1.7 1l.4 2.5h4l.4-2.5a7.6 7.6 0 0 0 1.7-1l2.3.9 2-3.4-2-1.5Z"/></svg>
        <span>تنظیمات</span>
      </button>
    </nav>

    <div class="sidebar-footer">
      <div class="current-admin">
        <span class="dot"></span>
        <?= $currentAdmin ?>
        <span class="role-badge role-badge--<?= htmlspecialchars($currentRole, ENT_QUOTES, 'UTF-8') ?>">
          <?= htmlspecialchars(resa_role_label($currentRole), ENT_QUOTES, 'UTF-8') ?>
        </span>
      </div>
      <a class="logout-btn" href="logout.php">خروج</a>
    </div>
  </aside>

  <main class="admin-main">

    <header class="admin-topbar">
      <button class="mobile-menu-btn" id="mobileMenuBtn" type="button" aria-label="منو">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
      </button>
      <h1 id="topbarTitle"><?= $tabTitles[$defaultTab] ?? 'پنل ادمین' ?></h1>
    </header>

    <?php if ($canSeeDashboard): ?>
    <?php include __DIR__ . '/tabs/tab-dashboard.php'; ?>
    <?php endif; ?>

    <?php if ($canSeeMessages): ?>
    <?php include __DIR__ . '/tabs/tab-messages.php'; ?>
    <?php endif; ?>

    <?php if ($canSeeProjects): ?>
    <?php include __DIR__ . '/tabs/tab-projects.php'; ?>
    <?php endif; ?>

    <?php if ($canSeeBlog): ?>
    <?php include __DIR__ . '/tabs/tab-articles.php'; ?>
    <?php endif; ?>

    <?php if ($canSeeComments): ?>
    <?php include __DIR__ . '/tabs/tab-comments.php'; ?>
    <?php endif; ?>

    <?php include __DIR__ . '/tabs/tab-email.php'; ?>

    <?php include __DIR__ . '/tabs/tab-settings.php'; ?>

  </main>
</div>

<?php include __DIR__ . '/modals/modal-message.php'; ?>
<?php include __DIR__ . '/modals/modal-comment-reply.php'; ?>
<?php include __DIR__ . '/modals/modal-edit-admin.php'; ?>
<?php include __DIR__ . '/modals/modal-category.php'; ?>
<?php include __DIR__ . '/modals/editor-article.php'; ?>
<?php include __DIR__ . '/modals/editor-project.php'; ?>
<?php include __DIR__ . '/modals/modal-brand.php'; ?>


<div class="toast" id="toast"></div>

<script src="assets/jalali-datepicker.js"></script>
<script src="assets/admin.js"></script>
<script src="assets/admin-dashboard.js"></script>
<script src="assets/admin-articles.js"></script>
<script src="assets/admin-projects.js"></script>
<script src="assets/admin-comments.js"></script>
<script src="assets/admin-pwa-install.js"></script>
<script src="assets/admin-settings.js"></script>
</body>
</html>