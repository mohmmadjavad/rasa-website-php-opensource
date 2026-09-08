<?php
/**
 * includes/auth.php
 * توابع مربوط به نشست (Session)، احراز هویت ادمین و توکن CSRF.
 */

if (!defined('RESA_APP')) {
    http_response_code(403);
    exit('Access denied.');
}

function resa_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('resa_admin_sid');
    session_start();
}

function resa_csrf_token(): string
{
    resa_start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function resa_csrf_check(?string $token): bool
{
    resa_start_session();
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function resa_is_logged_in(): bool
{
    resa_start_session();
    return !empty($_SESSION['admin_id']);
}

function resa_require_login(): void
{
    resa_start_session();
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
    // session fixation guard: regenerate id periodically
    if (empty($_SESSION['last_regen']) || (time() - $_SESSION['last_regen']) > 600) {
        session_regenerate_id(true);
        $_SESSION['last_regen'] = time();
    }
}

function resa_require_login_api(): void
{
    resa_start_session();
    if (empty($_SESSION['admin_id'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function resa_current_admin_id(): int
{
    resa_start_session();
    return (int)($_SESSION['admin_id'] ?? 0);
}

function resa_current_admin_role(): string
{
    resa_start_session();
    return $_SESSION['admin_role'] ?? 'admin';
}

function resa_is_super_admin(): bool
{
    return resa_current_admin_role() === 'super_admin';
}

/* ==================================================================
   سیستم دسترسی‌های سفارشی (granular permissions)
   -------------------------------------------------------------
   فقط دو نقش داریم: super_admin (دسترسی کامل و همیشگی) و admin
   (ادمین عادی) که دسترسی‌هایش با یک آبجکت JSON در ستون
   admins.permissions مشخص می‌شود. ساختار همیشه به‌صورت زیر نرمال
   می‌شود تا هیچ‌جای کد با کلید گمشده مواجه نشود:

   {
     "messages": { "view": bool, "delete": bool },
     "projects": { "access": bool },
     "blog":     { "access": bool, "scope": "own"|"all", "manage_categories": bool },
     "settings": { "manage_admins": bool }
   }
   ================================================================== */

function resa_default_permissions(): array
{
    return [
        'dashboard' => ['access' => true],
        'messages' => ['view' => false, 'delete' => false],
        'projects' => ['access' => false, 'scope' => 'own', 'manage_categories' => false],
        'blog'     => ['access' => true, 'scope' => 'own', 'manage_categories' => false],
        'comments' => ['access' => false, 'scope' => 'own'],
        'settings' => [
            'manage_admins' => false,
            'manage_site'   => false,
            'view_stats'    => false,
            'manage_backup' => false,
            'view_health'   => false,
        ],
    ];
}

function resa_full_permissions(): array
{
    return [
        'dashboard' => ['access' => true],
        'messages' => ['view' => true, 'delete' => true],
        'projects' => ['access' => true, 'scope' => 'all', 'manage_categories' => true],
        'blog'     => ['access' => true, 'scope' => 'all', 'manage_categories' => true],
        'comments' => ['access' => true, 'scope' => 'all'],
        'settings' => [
            'manage_admins' => true,
            'manage_site'   => true,
            'view_stats'    => true,
            'manage_backup' => true,
            'view_health'   => true,
        ],
    ];
}

/**
 * هر ورودی خام (JSON string، آرایه یا null) را به ساختار کامل و امن تبدیل می‌کند.
 */
function resa_normalize_permissions($raw): array
{
    $defaults = resa_default_permissions();
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? $decoded : [];
    } elseif (is_array($raw)) {
        $data = $raw;
    } else {
        $data = [];
    }

    $scope = $data['blog']['scope'] ?? $defaults['blog']['scope'];
    $scope = in_array($scope, ['own', 'all'], true) ? $scope : $defaults['blog']['scope'];

    $projectsScope = $data['projects']['scope'] ?? $defaults['projects']['scope'];
    $projectsScope = in_array($projectsScope, ['own', 'all'], true) ? $projectsScope : $defaults['projects']['scope'];

    $commentsScope = $data['comments']['scope'] ?? $defaults['comments']['scope'];
    $commentsScope = in_array($commentsScope, ['own', 'all'], true) ? $commentsScope : $defaults['comments']['scope'];

    return [
        'dashboard' => [
            'access' => (bool)($data['dashboard']['access'] ?? $defaults['dashboard']['access']),
        ],
        'messages' => [
            'view'   => (bool)($data['messages']['view'] ?? $defaults['messages']['view']),
            'delete' => (bool)($data['messages']['delete'] ?? $defaults['messages']['delete']),
        ],
        'projects' => [
            'access'            => (bool)($data['projects']['access'] ?? $defaults['projects']['access']),
            'scope'             => $projectsScope,
            'manage_categories' => (bool)($data['projects']['manage_categories'] ?? $defaults['projects']['manage_categories']),
        ],
        'blog' => [
            'access'            => (bool)($data['blog']['access'] ?? $defaults['blog']['access']),
            'scope'             => $scope,
            'manage_categories' => (bool)($data['blog']['manage_categories'] ?? $defaults['blog']['manage_categories']),
        ],
        'comments' => [
            'access' => (bool)($data['comments']['access'] ?? $defaults['comments']['access']),
            'scope'  => $commentsScope,
        ],
        'settings' => [
            'manage_admins' => (bool)($data['settings']['manage_admins'] ?? $defaults['settings']['manage_admins']),
            'manage_site'   => (bool)($data['settings']['manage_site'] ?? $defaults['settings']['manage_site']),
            'view_stats'    => (bool)($data['settings']['view_stats'] ?? $defaults['settings']['view_stats']),
            'manage_backup' => (bool)($data['settings']['manage_backup'] ?? $defaults['settings']['manage_backup']),
            'view_health'   => (bool)($data['settings']['view_health'] ?? $defaults['settings']['view_health']),
        ],
    ];
}

/**
 * دسترسی‌های نقش فعلی (سوپر ادمین همیشه دسترسی کامل دارد، صرف‌نظر از دیتابیس).
 */
function resa_current_admin_permissions(): array
{
    if (resa_is_super_admin()) {
        return resa_full_permissions();
    }
    resa_start_session();
    return resa_normalize_permissions($_SESSION['admin_permissions'] ?? null);
}

/**
 * نقش و دسترسی‌های ادمین را از دیتابیس تازه می‌کند تا تغییرات سوپر ادمین
 * (مثلاً ویرایش دسترسی‌ها) بدون نیاز به خروج و ورود دوباره اعمال شود.
 * اگر حساب ادمین حذف شده باشد، نشست را باطل کرده و به صفحه ورود می‌فرستد.
 */
function resa_sync_admin_session(PDO $pdo, bool $isApi = false): void
{
    resa_start_session();
    $id = (int)($_SESSION['admin_id'] ?? 0);
    if ($id <= 0) {
        return;
    }
    $stmt = $pdo->prepare('SELECT role, permissions FROM admins WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        $_SESSION = [];
        session_destroy();
        if ($isApi) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
        } else {
            header('Location: login.php');
        }
        exit;
    }
    $_SESSION['admin_role'] = $row['role'];
    $_SESSION['admin_permissions'] = resa_normalize_permissions($row['permissions']);
}

function resa_can_access_messages(): bool { return resa_current_admin_permissions()['messages']['view']; }
function resa_can_delete_messages(): bool { return resa_current_admin_permissions()['messages']['delete']; }
function resa_can_access_projects(): bool { return resa_current_admin_permissions()['projects']['access']; }
function resa_projects_scope(): string { return resa_current_admin_permissions()['projects']['scope']; }
function resa_can_manage_project_categories(): bool { return resa_current_admin_permissions()['projects']['manage_categories']; }
function resa_can_access_blog(): bool { return resa_current_admin_permissions()['blog']['access']; }
function resa_blog_scope(): string { return resa_current_admin_permissions()['blog']['scope']; }
function resa_can_manage_categories(): bool { return resa_current_admin_permissions()['blog']['manage_categories']; }
function resa_can_access_comments(): bool { return resa_current_admin_permissions()['comments']['access']; }
function resa_comments_scope(): string { return resa_current_admin_permissions()['comments']['scope']; }
function resa_can_manage_admins(): bool { return resa_current_admin_permissions()['settings']['manage_admins']; }
function resa_can_access_dashboard(): bool { return resa_current_admin_permissions()['dashboard']['access']; }
function resa_can_manage_site(): bool { return resa_current_admin_permissions()['settings']['manage_site']; }
function resa_can_view_stats(): bool { return resa_current_admin_permissions()['settings']['view_stats']; }
function resa_can_manage_backup(): bool { return resa_current_admin_permissions()['settings']['manage_backup']; }
function resa_can_view_health(): bool { return resa_current_admin_permissions()['settings']['view_health']; }

/**
 * آیا ادمین فعلی به یک وبلاگٔ مشخص (بر اساس نویسنده‌اش) دسترسی دارد؟
 * سوپر ادمین و ادمین با scope='all' به همه وبلاگ‌ها دسترسی دارند؛ در غیر این
 * صورت فقط به وبلاگ‌ها خودش.
 */
function resa_can_access_article(?int $authorAdminId): bool
{
    if (resa_is_super_admin() || resa_blog_scope() === 'all') {
        return true;
    }
    return $authorAdminId !== null && $authorAdminId === resa_current_admin_id();
}

/**
 * آیا ادمین فعلی به نظرات یک وبلاگٔ مشخص (بر اساس نویسنده‌اش) دسترسی دارد؟
 * سوپر ادمین و ادمین با scope='all' به نظرات همه وبلاگ‌ها دسترسی دارند؛ در غیر این
 * صورت فقط به نظرات وبلاگ‌ها خودش.
 */
function resa_can_access_article_comments(?int $authorAdminId): bool
{
    if (resa_is_super_admin() || resa_comments_scope() === 'all') {
        return true;
    }
    return $authorAdminId !== null && $authorAdminId === resa_current_admin_id();
}

/**
 * آیا ادمین فعلی به یک پروژه‌ی مشخص (بر اساس لیست ادمین‌های تیم آن) دسترسی دارد؟
 * سوپر ادمین و ادمین با scope='all' به همه‌ی پروژه‌ها دسترسی دارند؛ در غیر این
 * صورت فقط پروژه‌هایی که خودش جزو تیمشان انتخاب شده.
 */
function resa_can_access_project(array $teamAdminIds): bool
{
    if (resa_is_super_admin() || resa_projects_scope() === 'all') {
        return true;
    }
    return in_array(resa_current_admin_id(), $teamAdminIds, true);
}

/**
 * برچسب فارسی مربوط به هر نقش، برای نمایش در رابط کاربری.
 */
function resa_role_label(string $role): string
{
    return $role === 'super_admin' ? 'سوپر ادمین' : 'ادمین';
}

function resa_api_forbidden(string $message = 'شما دسترسی لازم برای این عملیات را ندارید.'): void
{
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * برای اندپوینت‌های API که فقط سوپر ادمین اجازه دسترسی دارد.
 */
function resa_require_super_admin_api(): void
{
    if (!resa_is_super_admin()) {
        resa_api_forbidden();
    }
}

function resa_require_messages_view_api(): void
{
    if (!resa_is_super_admin() && !resa_can_access_messages()) {
        resa_api_forbidden('شما دسترسی مشاهده پیام‌ها را ندارید.');
    }
}

function resa_require_messages_delete_api(): void
{
    if (!resa_is_super_admin() && !resa_can_delete_messages()) {
        resa_api_forbidden('شما دسترسی حذف پیام‌ها را ندارید.');
    }
}

function resa_require_projects_api(): void
{
    if (!resa_is_super_admin() && !resa_can_access_projects()) {
        resa_api_forbidden('شما دسترسی بخش پروژه‌ها را ندارید.');
    }
}

function resa_require_blog_api(): void
{
    if (!resa_is_super_admin() && !resa_can_access_blog()) {
        resa_api_forbidden('شما دسترسی بخش وبلاگ‌ها را ندارید.');
    }
}

function resa_require_manage_project_categories_api(): void
{
    if (!resa_is_super_admin() && !resa_can_manage_project_categories()) {
        resa_api_forbidden('شما دسترسی ساخت دسته‌بندی پروژه را ندارید.');
    }
}

function resa_require_manage_categories_api(): void
{
    if (!resa_is_super_admin() && !resa_can_manage_categories()) {
        resa_api_forbidden('شما دسترسی ساخت دسته‌بندی را ندارید.');
    }
}

function resa_require_dashboard_api(): void
{
    if (!resa_is_super_admin() && !resa_can_access_dashboard()) {
        resa_api_forbidden('شما اجازه‌ی مشاهده‌ی داشبورد را ندارید.');
    }
}

function resa_require_manage_site_api(): void
{
    if (!resa_is_super_admin() && !resa_can_manage_site()) {
        resa_api_forbidden('شما دسترسی تنظیمات سایت را ندارید.');
    }
}

function resa_require_view_stats_api(): void
{
    if (!resa_is_super_admin() && !resa_can_view_stats()) {
        resa_api_forbidden('شما دسترسی مشاهده‌ی آمار و گزارش را ندارید.');
    }
}

function resa_require_manage_backup_api(): void
{
    if (!resa_is_super_admin() && !resa_can_manage_backup()) {
        resa_api_forbidden('شما دسترسی پشتیبان‌گیری را ندارید.');
    }
}

function resa_require_view_health_api(): void
{
    if (!resa_is_super_admin() && !resa_can_view_health()) {
        resa_api_forbidden('شما دسترسی مشاهده‌ی سلامت سیستم را ندارید.');
    }
}

function resa_require_comments_api(): void
{
    if (!resa_is_super_admin() && !resa_can_access_comments()) {
        resa_api_forbidden('شما دسترسی بخش نظرات کاربران را ندارید.');
    }
}

function resa_require_manage_admins_api(): void
{
    if (!resa_is_super_admin() && !resa_can_manage_admins()) {
        resa_api_forbidden('شما دسترسی مدیریت ادمین‌ها را ندارید.');
    }
}

function resa_client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * بررسی تعداد تلاش‌های ناموفق ورود اخیر برای یک شناسه (IP یا نام کاربری)
 */
function resa_login_is_locked(PDO $pdo, string $identifier): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND attempted_at > (NOW() - INTERVAL " . (int)LOGIN_LOCK_SECONDS . " SECOND)");
    $stmt->execute([$identifier]);
    return (int)$stmt->fetchColumn() >= LOGIN_MAX_ATTEMPTS;
}

function resa_login_record_attempt(PDO $pdo, string $identifier): void
{
    $stmt = $pdo->prepare("INSERT INTO login_attempts (identifier, attempted_at) VALUES (?, NOW())");
    $stmt->execute([$identifier]);
}

function resa_login_clear_attempts(PDO $pdo, string $identifier): void
{
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE identifier = ?");
    $stmt->execute([$identifier]);
}
