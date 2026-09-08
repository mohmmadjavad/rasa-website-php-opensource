<?php
/**
 * includes/db.php
 * اتصال به دیتابیس با PDO + ساخت خودکار جداول در صورت نبودن.
 */

if (!defined('RESA_APP')) {
    http_response_code(403);
    exit('Access denied.');
}

function resa_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        exit('خطا در اتصال به دیتابیس. تنظیمات config.php را بررسی کنید.');
    }

    resa_migrate($pdo);
    return $pdo;
}

function resa_migrate(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(60) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* ---------- ارتقای جدول admins: نقش + دسترسی‌های سفارشی + پروفایل عمومی نویسنده ---------- */
    $roleColumnIsNew = resa_ensure_column($pdo, 'admins', 'role', "ENUM('super_admin','admin') NOT NULL DEFAULT 'admin'");
    resa_ensure_column($pdo, 'admins', 'permissions', "TEXT DEFAULT NULL");
    resa_migrate_role_system($pdo);
    resa_ensure_column($pdo, 'admins', 'display_name', "VARCHAR(100) DEFAULT NULL");
    resa_ensure_column($pdo, 'admins', 'avatar', "VARCHAR(255) DEFAULT NULL");
    resa_ensure_column($pdo, 'admins', 'bio', "VARCHAR(600) DEFAULT NULL");
    $bioFullIsNew = resa_ensure_column($pdo, 'admins', 'bio_full', "TEXT DEFAULT NULL");
    resa_ensure_column($pdo, 'admins', 'bio_short', "VARCHAR(240) DEFAULT NULL");
    if ($bioFullIsNew) {
        // انتقال بیوگرافی قدیمیِ تک‌فیلدی به «بیوگرافی مفصل» جدید (سازگاری با داده‌های قبلی)
        $pdo->exec("UPDATE admins SET bio_full = bio WHERE bio IS NOT NULL AND bio_full IS NULL");
    }
    resa_ensure_column($pdo, 'admins', 'social_telegram', "VARCHAR(150) DEFAULT NULL");
    resa_ensure_column($pdo, 'admins', 'social_instagram', "VARCHAR(150) DEFAULT NULL");
    resa_ensure_column($pdo, 'admins', 'social_email', "VARCHAR(150) DEFAULT NULL");
    resa_ensure_column($pdo, 'admins', 'social_phone', "VARCHAR(50) DEFAULT NULL");
    resa_ensure_column($pdo, 'admins', 'social_linkedin', "VARCHAR(150) DEFAULT NULL");
    resa_ensure_column($pdo, 'admins', 'social_x', "VARCHAR(150) DEFAULT NULL");
    resa_ensure_column($pdo, 'admins', 'social_whatsapp', "VARCHAR(150) DEFAULT NULL");
    resa_ensure_column($pdo, 'admins', 'social_github', "VARCHAR(150) DEFAULT NULL");
    resa_ensure_column($pdo, 'admins', 'social_youtube', "VARCHAR(150) DEFAULT NULL");
    resa_ensure_column($pdo, 'admins', 'social_website', "VARCHAR(150) DEFAULT NULL");
    resa_ensure_column($pdo, 'admins', 'social_pinterest', "VARCHAR(150) DEFAULT NULL");
    resa_ensure_column($pdo, 'admins', 'social_custom_label', "VARCHAR(60) DEFAULT NULL");
    resa_ensure_column($pdo, 'admins', 'social_custom_url', "VARCHAR(255) DEFAULT NULL");

    /* ---------- کارت «تیم ما»: هر ادمین برای خودش تعریف می‌کند، سوپر ادمین ویرایش/مرتب‌سازی می‌کند ---------- */
    resa_ensure_column($pdo, 'admins', 'job_title', "VARCHAR(160) DEFAULT NULL");
    resa_ensure_column($pdo, 'admins', 'card_image', "VARCHAR(255) DEFAULT NULL");
    resa_ensure_column($pdo, 'admins', 'team_card_enabled', "TINYINT(1) NOT NULL DEFAULT 1");
    resa_ensure_column($pdo, 'admins', 'team_card_sort_order', "INT UNSIGNED NOT NULL DEFAULT 0");

    /* ---------- بخش «افراد توسعه دهنده» (صفحه تماس با ما): سوپر ادمین انتخاب می‌کند کدام ادمین آنجا نمایش داده شود ---------- */
    resa_ensure_column($pdo, 'admins', 'dev_team_enabled', "TINYINT(1) NOT NULL DEFAULT 0");
    resa_ensure_column($pdo, 'admins', 'dev_team_sort_order', "INT UNSIGNED NOT NULL DEFAULT 0");
    if ($roleColumnIsNew) {
        // ادمین‌هایی که پیش از افزودن سیستم نقش وجود داشتند، به‌صورت خودکار سوپر ادمین می‌شوند.
        $pdo->exec("UPDATE admins SET role = 'super_admin'");
    }

    /* ---------- اعضای کارت تیم بدون حساب ادمین (سوپر ادمین دستی اضافه می‌کند) ----------
       توجه مهم: فقط ستون‌های پایه اینجا با CREATE TABLE ساخته می‌شوند. چون
       CREATE TABLE IF NOT EXISTS وقتی جدول از قبل وجود داشته باشد کاملاً نادیده گرفته
       می‌شود (حتی اگر متن CREATE بعداً ستون جدید بگیرد)، هر ستون دیگری باید حتماً با
       resa_ensure_column (ALTER) اضافه شود تا روی نصب‌های قدیمی هم واقعاً ساخته شود. */
    $pdo->exec("CREATE TABLE IF NOT EXISTS team_extra_members (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    resa_ensure_column($pdo, 'team_extra_members', 'job_title', "VARCHAR(160) DEFAULT NULL");
    resa_ensure_column($pdo, 'team_extra_members', 'card_image', "VARCHAR(255) DEFAULT NULL");
    resa_ensure_column($pdo, 'team_extra_members', 'bio_short', "VARCHAR(240) DEFAULT NULL");
    resa_ensure_column($pdo, 'team_extra_members', 'social_telegram', "VARCHAR(150) DEFAULT NULL");
    resa_ensure_column($pdo, 'team_extra_members', 'social_instagram', "VARCHAR(150) DEFAULT NULL");
    resa_ensure_column($pdo, 'team_extra_members', 'social_whatsapp', "VARCHAR(150) DEFAULT NULL");
    resa_ensure_column($pdo, 'team_extra_members', 'social_github', "VARCHAR(150) DEFAULT NULL");
    resa_ensure_column($pdo, 'team_extra_members', 'social_linkedin', "VARCHAR(150) DEFAULT NULL");
    resa_ensure_column($pdo, 'team_extra_members', 'social_x', "VARCHAR(150) DEFAULT NULL");
    resa_ensure_column($pdo, 'team_extra_members', 'social_youtube', "VARCHAR(150) DEFAULT NULL");
    resa_ensure_column($pdo, 'team_extra_members', 'social_website', "VARCHAR(150) DEFAULT NULL");
    resa_ensure_column($pdo, 'team_extra_members', 'social_pinterest', "VARCHAR(150) DEFAULT NULL");
    resa_ensure_column($pdo, 'team_extra_members', 'social_custom_label', "VARCHAR(60) DEFAULT NULL");
    resa_ensure_column($pdo, 'team_extra_members', 'social_custom_url', "VARCHAR(255) DEFAULT NULL");
    resa_ensure_column($pdo, 'team_extra_members', 'social_email', "VARCHAR(150) DEFAULT NULL");
    resa_ensure_column($pdo, 'team_extra_members', 'social_phone', "VARCHAR(50) DEFAULT NULL");
    resa_ensure_column($pdo, 'team_extra_members', 'is_enabled', "TINYINT(1) NOT NULL DEFAULT 1");
    resa_ensure_column($pdo, 'team_extra_members', 'sort_order', "INT UNSIGNED NOT NULL DEFAULT 0");

    /* ---------- بخش «دستاوردی چشم‌نواز / ویترین افتخارات رسا» (خانه + درباره ما): مدیریت داینامیک تصاویر جام‌ها ---------- */
    $pdo->exec("CREATE TABLE IF NOT EXISTS awards (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        image VARCHAR(255) NOT NULL,
        title VARCHAR(160) DEFAULT NULL,
        sort_order INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(150) NOT NULL,
        contact_info VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        identifier VARCHAR(120) NOT NULL,
        attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* محدودسازی نرخ درخواست برای فرم‌های عمومی (تماس با ما، ارسال دیدگاه)
       بر اساس IP — مستقل از سشن، چون سشن با پاک‌کردن کوکی به‌سادگی دور زده می‌شود */
    $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limit_hits (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        action VARCHAR(60) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        hit_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_action_ip_time (action, ip_address, hit_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* ---------- ارتقای جدول projects به ساختار کامل بخش پروژه‌ها ---------- */
    resa_ensure_column($pdo, 'projects', 'slug', "VARCHAR(220) NOT NULL DEFAULT ''");
    resa_ensure_column($pdo, 'projects', 'excerpt', "VARCHAR(500) DEFAULT NULL");
    resa_ensure_column($pdo, 'projects', 'content', "LONGTEXT");
    resa_ensure_column($pdo, 'projects', 'cover_type', "ENUM('image','video') NOT NULL DEFAULT 'image'");
    resa_ensure_column($pdo, 'projects', 'cover_image', "VARCHAR(255) DEFAULT NULL");
    resa_ensure_column($pdo, 'projects', 'cover_video', "VARCHAR(500) DEFAULT NULL");
    resa_ensure_column($pdo, 'projects', 'category_id', "INT UNSIGNED DEFAULT NULL");
    resa_ensure_column($pdo, 'projects', 'status', "ENUM('draft','published') NOT NULL DEFAULT 'draft'");
    resa_ensure_column($pdo, 'projects', 'views', "INT UNSIGNED NOT NULL DEFAULT 0");
    resa_ensure_column($pdo, 'projects', 'meta_title', "VARCHAR(180) DEFAULT NULL");
    resa_ensure_column($pdo, 'projects', 'meta_description', "VARCHAR(320) DEFAULT NULL");
    resa_ensure_column($pdo, 'projects', 'created_by_admin_id', "INT UNSIGNED DEFAULT NULL");
    resa_ensure_column($pdo, 'projects', 'published_at', "DATETIME DEFAULT NULL");
    resa_ensure_column($pdo, 'projects', 'updated_at', "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    /* کجا منتشر شود: فقط صفحه پروژه‌های سایت، فقط صفحه پروفایل ادمین، یا هر دو (پیش‌فرض) */
    resa_ensure_column($pdo, 'projects', 'display_scope', "ENUM('projects_page','profile_only','both') NOT NULL DEFAULT 'both'");

    /* ---------- تیم پروژه: ادمین‌های انتخاب‌شده از بین ادمین‌های سایت ---------- */
    $pdo->exec("CREATE TABLE IF NOT EXISTS project_admins (
        project_id INT UNSIGNED NOT NULL,
        admin_id INT UNSIGNED NOT NULL,
        sort_order INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (project_id, admin_id),
        CONSTRAINT fk_padm_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        CONSTRAINT fk_padm_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* ---------- دسته‌بندی‌های پروژه (چند-انتخابی) ---------- */
    /* توجه: این جدول باید بعد از ساخت جدول categories ایجاد شود چون به آن
       کلید خارجی دارد؛ به همین دلیل ساخت آن پایین‌تر، بعد از categories، انجام می‌شود. */

    /* ---------- تیم پروژه: افرادی که دستی (بدون حساب ادمین) اضافه می‌شوند ---------- */
    $pdo->exec("CREATE TABLE IF NOT EXISTS project_members (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        project_id INT UNSIGNED NOT NULL,
        name VARCHAR(150) NOT NULL,
        role_title VARCHAR(150) DEFAULT NULL,
        avatar VARCHAR(255) DEFAULT NULL,
        sort_order INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_pmem_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* ---------- دسته‌بندی وبلاگ‌ها (اصلی + زیرمجموعه) — با فیلد نوع، مشترک بین وبلاگ‌ها و پروژه‌ها ---------- */
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(160) NOT NULL UNIQUE,
        parent_id INT UNSIGNED DEFAULT NULL,
        icon_image VARCHAR(255) DEFAULT NULL,
        poster_image VARCHAR(255) DEFAULT NULL,
        description VARCHAR(500) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_parent (parent_id),
        CONSTRAINT fk_cat_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    resa_ensure_column($pdo, 'categories', 'icon_image', "VARCHAR(255) DEFAULT NULL");
    resa_ensure_column($pdo, 'categories', 'poster_image', "VARCHAR(255) DEFAULT NULL");
    resa_ensure_column($pdo, 'categories', 'description', "VARCHAR(500) DEFAULT NULL");
    resa_ensure_column($pdo, 'categories', 'type', "ENUM('blog','project') NOT NULL DEFAULT 'blog'");

    /* ---------- دسته‌بندی‌های پروژه (چند-انتخابی) — اینجا ساخته می‌شود چون به categories کلید خارجی دارد ---------- */
    $pdo->exec("CREATE TABLE IF NOT EXISTS project_categories (
        project_id INT UNSIGNED NOT NULL,
        category_id INT UNSIGNED NOT NULL,
        PRIMARY KEY (project_id, category_id),
        CONSTRAINT fk_pcat_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        CONSTRAINT fk_pcat_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* ---------- برندهایی که با آن‌ها کار کرده‌ایم (چند-انتخابی برای پروژه) ---------- */
    $pdo->exec("CREATE TABLE IF NOT EXISTS brands (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(160) NOT NULL UNIQUE,
        logo_image VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    resa_ensure_column($pdo, 'brands', 'logo_image', "VARCHAR(255) NULL AFTER slug");

    $pdo->exec("CREATE TABLE IF NOT EXISTS project_brands (
        project_id INT UNSIGNED NOT NULL,
        brand_id INT UNSIGNED NOT NULL,
        PRIMARY KEY (project_id, brand_id),
        CONSTRAINT fk_pbrand_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        CONSTRAINT fk_pbrand_brand FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* ---------- تگ‌ها ---------- */
    $pdo->exec("CREATE TABLE IF NOT EXISTS tags (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(60) NOT NULL,
        slug VARCHAR(90) NOT NULL UNIQUE,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* ---------- وبلاگ‌ها ---------- */
    $pdo->exec("CREATE TABLE IF NOT EXISTS articles (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(220) NOT NULL,
        slug VARCHAR(220) NOT NULL UNIQUE,
        excerpt VARCHAR(500) DEFAULT NULL,
        content LONGTEXT,
        cover_image VARCHAR(255) DEFAULT NULL,
        author VARCHAR(100) DEFAULT NULL,
        author_admin_id INT UNSIGNED DEFAULT NULL,
        category_id INT UNSIGNED DEFAULT NULL,
        status ENUM('draft','published') NOT NULL DEFAULT 'draft',
        reading_time INT UNSIGNED NOT NULL DEFAULT 1,
        views INT UNSIGNED NOT NULL DEFAULT 0,
        meta_title VARCHAR(180) DEFAULT NULL,
        meta_description VARCHAR(320) DEFAULT NULL,
        focus_keyword VARCHAR(120) DEFAULT NULL,
        published_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_category (category_id),
        KEY idx_status (status),
        KEY idx_published (published_at),
        KEY idx_author_admin (author_admin_id),
        CONSTRAINT fk_art_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
        CONSTRAINT fk_art_author FOREIGN KEY (author_admin_id) REFERENCES admins(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* ---------- ارتباط وبلاگ با زیردسته‌ها (چندتایی) ---------- */
    $pdo->exec("CREATE TABLE IF NOT EXISTS article_subcategories (
        article_id INT UNSIGNED NOT NULL,
        category_id INT UNSIGNED NOT NULL,
        PRIMARY KEY (article_id, category_id),
        CONSTRAINT fk_asub_article FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
        CONSTRAINT fk_asub_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* ---------- ارتباط وبلاگ با تگ‌ها ---------- */
    $pdo->exec("CREATE TABLE IF NOT EXISTS article_tags (
        article_id INT UNSIGNED NOT NULL,
        tag_id INT UNSIGNED NOT NULL,
        PRIMARY KEY (article_id, tag_id),
        CONSTRAINT fk_atag_article FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
        CONSTRAINT fk_atag_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* ---------- ارتقای جدول articles قدیمی (در صورت وجود نسخه‌ی ابتدایی) ---------- */
    resa_ensure_column($pdo, 'articles', 'slug', "VARCHAR(220) NOT NULL DEFAULT ''");
    resa_ensure_column($pdo, 'articles', 'excerpt', "VARCHAR(500) DEFAULT NULL");
    resa_ensure_column($pdo, 'articles', 'content', "LONGTEXT");
    resa_ensure_column($pdo, 'articles', 'cover_image', "VARCHAR(255) DEFAULT NULL");
    resa_ensure_column($pdo, 'articles', 'author', "VARCHAR(100) DEFAULT NULL");
    resa_ensure_column($pdo, 'articles', 'author_admin_id', "INT UNSIGNED DEFAULT NULL");
    resa_ensure_column($pdo, 'articles', 'category_id', "INT UNSIGNED DEFAULT NULL");
    resa_ensure_column($pdo, 'articles', 'status', "ENUM('draft','published') NOT NULL DEFAULT 'draft'");
    resa_ensure_column($pdo, 'articles', 'reading_time', "INT UNSIGNED NOT NULL DEFAULT 1");
    resa_ensure_column($pdo, 'articles', 'views', "INT UNSIGNED NOT NULL DEFAULT 0");
    resa_ensure_column($pdo, 'articles', 'meta_title', "VARCHAR(180) DEFAULT NULL");
    resa_ensure_column($pdo, 'articles', 'meta_description', "VARCHAR(320) DEFAULT NULL");
    resa_ensure_column($pdo, 'articles', 'focus_keyword', "VARCHAR(120) DEFAULT NULL");
    resa_ensure_column($pdo, 'articles', 'published_at', "DATETIME DEFAULT NULL");
    resa_ensure_column($pdo, 'articles', 'updated_at', "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

    /* ---------- نظرات کاربران روی وبلاگ‌ها (با پاسخ‌های تودرتو) ---------- */
    $pdo->exec("CREATE TABLE IF NOT EXISTS comments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        article_id INT UNSIGNED NOT NULL,
        parent_id INT UNSIGNED DEFAULT NULL,
        author_type ENUM('user','admin') NOT NULL DEFAULT 'user',
        admin_id INT UNSIGNED DEFAULT NULL,
        user_name VARCHAR(100) DEFAULT NULL,
        content TEXT NOT NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_article (article_id),
        KEY idx_parent (parent_id),
        KEY idx_status (status),
        KEY idx_admin (admin_id),
        CONSTRAINT fk_comment_article FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
        CONSTRAINT fk_comment_parent FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE,
        CONSTRAINT fk_comment_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    resa_ensure_column($pdo, 'comments', 'spam_reason', "VARCHAR(255) DEFAULT NULL");
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
        setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
        setting_value TEXT DEFAULT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* ---------- گزارش فعالیت‌های امنیتی/مدیریتی پنل ادمین ---------- */
    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        admin_id INT UNSIGNED DEFAULT NULL,
        admin_username VARCHAR(60) DEFAULT NULL,
        action VARCHAR(60) NOT NULL,
        description VARCHAR(500) DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_created (created_at),
        KEY idx_admin (admin_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* ---------- صندوق ایمیل هر ادمین (mailbox ساخته‌شده روی cPanel) ---------- */
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_mailboxes (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        admin_id INT UNSIGNED NOT NULL,
        local_part VARCHAR(60) NOT NULL,
        domain VARCHAR(120) NOT NULL,
        email_address VARCHAR(190) NOT NULL,
        password_encrypted TEXT NOT NULL,
        status ENUM('active','failed','deleted') NOT NULL DEFAULT 'active',
        last_error VARCHAR(500) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_email (email_address),
        UNIQUE KEY uniq_admin (admin_id),
        CONSTRAINT fk_mailbox_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* ---------- لاگ پاکسازی خودکار پیوست‌های قدیمی (برای پیگیری/دیباگ کرون) ---------- */
    $pdo->exec("CREATE TABLE IF NOT EXISTS mail_cleanup_log (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        admin_id INT UNSIGNED DEFAULT NULL,
        messages_scanned INT UNSIGNED NOT NULL DEFAULT 0,
        attachments_removed INT UNSIGNED NOT NULL DEFAULT 0,
        ran_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        note VARCHAR(500) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* ---------- بازدیدهای صفحات سایت (برای آمار سراسری در پنل ادمین) ---------- */
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_visits (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        page_path VARCHAR(255) NOT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_created (created_at),
        KEY idx_page (page_path(120))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* ---------- شمارنده‌ی دائمی بازدید هر صفحه ----------
       ردیف‌های خام site_visits به‌طور دوره‌ای پاک‌سازی می‌شوند (کرون
       cron/logs-cleanup-cron.php) تا آن جدول رشد بی‌رویه نداشته باشد؛
       اما «تعداد کل بازدید» و «پربازدیدترین صفحات» باید برای همیشه دقیق
       بمانند. برای همین این شمارنده مستقل (یک ردیف به ازای هر مسیر،
       نه هر بازدید) همزمان با هر بازدید به‌روزرسانی می‌شود و هیچ‌وقت
       پاک نمی‌شود. */
    $pdo->exec("CREATE TABLE IF NOT EXISTS page_view_totals (
        page_path VARCHAR(255) NOT NULL PRIMARY KEY,
        total_views INT UNSIGNED NOT NULL DEFAULT 0,
        first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * ثبت یک رویداد در گزارش فعالیت‌های پنل ادمین (لاگ امنیتی/مدیریتی).
 * این تابع هیچ‌وقت نباید باعث خطا در عملیات اصلی شود، پس خطاهای احتمالی
 * را نادیده می‌گیرد.
 */
function resa_log_activity(PDO $pdo, ?int $adminId, ?string $username, string $action, string $description = ''): void
{
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (admin_id, admin_username, action, description, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $adminId ?: null,
            $username !== '' ? $username : null,
            $action,
            $description !== '' ? $description : null,
            resa_client_ip(),
        ]);
    } catch (Throwable $e) {
        // ثبت لاگ نباید مانع از انجام عملیات اصلی شود
    }
}

/**
 * ثبت سبک یک بازدید صفحه از سایت (برای آمار پنل ادمین). خطاهای احتمالی
 * (مثلاً دیتابیس موقتاً در دسترس نبود) نادیده گرفته می‌شوند تا تجربه
 * کاربر عادی سایت هیچ‌وقت مختل نشود.
 */
function resa_track_visit(PDO $pdo, string $path): void
{
    try {
        $path = trim($path);
        if ($path === '') {
            $path = '/';
        }
        if (mb_strlen($path) > 255) {
            $path = mb_substr($path, 0, 255);
        }
        $stmt = $pdo->prepare("INSERT INTO site_visits (page_path, ip_address, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$path, resa_client_ip()]);

        // شمارنده‌ی دائمی، مستقل از پاک‌سازی دوره‌ای ردیف‌های خام بالا
        $pdo->prepare("INSERT INTO page_view_totals (page_path, total_views, first_seen, last_seen) VALUES (?, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE total_views = total_views + 1, last_seen = NOW()")
            ->execute([$path]);
    } catch (Throwable $e) {
        // نادیده گرفتن خطا — ردیابی بازدید نباید تجربه کاربر را مختل کند
    }
}

/**
 * آمار خلاصه بازدید سایت برای نمایش در تب تنظیمات پنل ادمین.
 */
function resa_get_visit_stats(PDO $pdo): array
{
    // «تعداد کل» و «پربازدیدترین صفحات» از شمارنده‌ی دائمی خوانده می‌شوند
    // (نه از site_visits خام) چون ردیف‌های خام به‌مرور پاک‌سازی می‌شوند.
    $total = (int)$pdo->query("SELECT COALESCE(SUM(total_views), 0) FROM page_view_totals")->fetchColumn();
    $today = (int)$pdo->query("SELECT COUNT(*) FROM site_visits WHERE DATE(created_at) = CURDATE()")->fetchColumn();

    $byDate = [];
    $stmt = $pdo->prepare("SELECT DATE(created_at) AS d, COUNT(*) AS c FROM site_visits WHERE created_at >= (NOW() - INTERVAL 6 DAY) GROUP BY DATE(created_at)");
    $stmt->execute();
    foreach ($stmt->fetchAll() as $row) {
        $byDate[(string)$row['d']] = (int)$row['c'];
    }

    $last7Days = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} day"));
        $last7Days[] = ['date' => $d, 'count' => $byDate[$d] ?? 0];
    }

    $topPagesStmt = $pdo->query("SELECT page_path, total_views AS c FROM page_view_totals ORDER BY total_views DESC LIMIT 6");
    $topPages = $topPagesStmt->fetchAll();

    return [
        'total'       => $total,
        'today'       => $today,
        'last_7_days' => $last7Days,
        'top_pages'   => $topPages,
    ];
}

/**
 * مسیر فایل «قفل حالت تعمیرات» در ریشه سایت. وجود این فایل توسط
 * .htaccess چک می‌شود تا بدون نیاز به اجرای PHP، صفحات سایت به
 * maintenance.php هدایت شوند.
 */
function resa_maintenance_flag_path(): string
{
    return dirname(__DIR__) . '/maintenance.flag';
}

/**
 * روشن/خاموش کردن فایل قفل حالت تعمیرات (منبع تشخیص سریع برای .htaccess).
 * منبع اصلی و همیشگیِ وضعیت، همچنان تنظیم «maintenance_mode» در جدول
 * site_settings است؛ این فایل فقط یک کش سریع برای وب‌سرور است.
 */
function resa_set_maintenance_flag(bool $on): void
{
    $path = resa_maintenance_flag_path();
    if ($on) {
        @file_put_contents($path, (string)time());
    } elseif (file_exists($path)) {
        @unlink($path);
    }
}

/**
 * خواندن یک تنظیم سراسری سایت از جدول site_settings.
 */
function resa_get_setting(PDO $pdo, string $key, ?string $default = null): ?string
{
    $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1");
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    return $val === false ? $default : $val;
}

/**
 * ذخیره یک تنظیم سراسری سایت در جدول site_settings.
 */
function resa_set_setting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$key, $value]);
}

/**
 * ارتقای دیتابیس‌های قدیمی: نقش‌های قبلی (blog_admin و comment_admin) را به
 * نقش واحد 'admin' همراه با یک آبجکت permissions معادل تبدیل می‌کند تا
 * دسترسی‌های قبلی هر ادمین حفظ شود. اگر دیتابیس از قبل با ساختار جدید
 * ساخته شده باشد، این تابع کاری انجام نمی‌دهد.
 */
function resa_migrate_role_system(PDO $pdo): void
{
    $stmt = $pdo->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'role'");
    $stmt->execute();
    $type = (string)$stmt->fetchColumn();

    $hasOldRoles = strpos($type, 'blog_admin') !== false || strpos($type, 'comment_admin') !== false;
    if (!$hasOldRoles) {
        return;
    }

    // نقش موقتاً به ENUM سه‌گانه‌ی قدیمی برمی‌گردیم تا بشود مقادیر قبلی را خواند (اگر قبلاً تنگ‌تر نشده)
    // سپس بر اساس نقش قبلی، یک permissions معادل می‌سازیم و نقش را به 'admin' تبدیل می‌کنیم.
    $commentAdminPerms = json_encode([
        'messages' => ['view' => true, 'delete' => true],
        'projects' => ['access' => false],
        'blog'     => ['access' => true, 'scope' => 'own', 'manage_categories' => true],
        'settings' => ['manage_admins' => false],
    ], JSON_UNESCAPED_UNICODE);

    $blogAdminPerms = json_encode([
        'messages' => ['view' => false, 'delete' => false],
        'projects' => ['access' => false],
        'blog'     => ['access' => true, 'scope' => 'own', 'manage_categories' => true],
        'settings' => ['manage_admins' => false],
    ], JSON_UNESCAPED_UNICODE);

    $upd1 = $pdo->prepare("UPDATE admins SET permissions = ? WHERE role = 'comment_admin' AND permissions IS NULL");
    $upd1->execute([$commentAdminPerms]);

    $upd2 = $pdo->prepare("UPDATE admins SET permissions = ? WHERE role = 'blog_admin' AND permissions IS NULL");
    $upd2->execute([$blogAdminPerms]);

    $pdo->exec("UPDATE admins SET role = 'admin' WHERE role IN ('blog_admin','comment_admin')");
    $pdo->exec("ALTER TABLE admins MODIFY COLUMN role ENUM('super_admin','admin') NOT NULL DEFAULT 'admin'");
}

/**
 * ستون را در صورت نبودن به جدول اضافه می‌کند (برای ارتقای دیتابیس‌های قدیمی).
 * خروجی: true اگر ستون تازه اضافه شده باشد، false اگر از قبل وجود داشته.
 */
function resa_ensure_column(PDO $pdo, string $table, string $column, string $definition): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) return false;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    $wasAdded = false;
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        $wasAdded = true;
    }
    $cache[$key] = true;
    return $wasAdded;
}

/**
 * محدودسازی نرخ درخواست بر اساس IP، مستقل از سشن (لایه‌ی دفاعی دوم علاوه
 * بر throttle سمت سشن و کپچا). هر بار فراخوانی، هم بررسی می‌کند و هم
 * (در صورت مجاز بودن) یک رکورد جدید ثبت می‌کند.
 *
 * @return true اگر درخواست مجاز است، false اگر از سقف مجاز عبور کرده.
 */
function resa_rate_limit_ok(PDO $pdo, string $action, int $maxHits = 5, int $windowSeconds = 300): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // پاک‌سازی رکوردهای قدیمی‌تر از پنجره‌ی زمانی (برای جلوگیری از رشد بی‌رویه جدول)
    $pdo->prepare("DELETE FROM rate_limit_hits WHERE action = ? AND hit_at < (NOW() - INTERVAL ? SECOND)")
        ->execute([$action, $windowSeconds]);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rate_limit_hits WHERE action = ? AND ip_address = ? AND hit_at >= (NOW() - INTERVAL ? SECOND)");
    $stmt->execute([$action, $ip, $windowSeconds]);
    $count = (int)$stmt->fetchColumn();

    if ($count >= $maxHits) {
        return false;
    }

    $pdo->prepare("INSERT INTO rate_limit_hits (action, ip_address) VALUES (?, ?)")->execute([$action, $ip]);
    return true;
}