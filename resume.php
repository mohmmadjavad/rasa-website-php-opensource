<?php
/**
 * resume.php
 * رندر سمت‌سرور متاتگ‌ها/OG/JSON-LD برای پروفایل نویسنده (طرح ۲ ستونه دسکتاپ).
 */
define('RESA_APP', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/seo-helpers.php';

$usernameParam = trim((string)($_GET['user'] ?? ''));
$author = null;

if ($usernameParam !== '') {
    try {
        $pdo = resa_db();
        $stmt = $pdo->prepare("SELECT id, username, display_name, avatar, job_title, bio_short FROM admins WHERE username = ?");
        $stmt->execute([$usernameParam]);
        $author = $stmt->fetch();
    } catch (Throwable $e) {
        $author = null;
    }
}

if ($author) {
    $name = $author['display_name'] ?: $author['username'];
    resa_print_meta([
        'title' => $name,
        'description' => $author['bio_short'] ?: ($author['job_title'] ? $author['job_title'] . ' — ' . SITE_NAME : (SITE_NAME . ' — پروفایل ' . $name)),
        'url' => '/resume.html?user=' . rawurlencode($author['username']),
        'image' => $author['avatar'],
        'with_ids' => true,
    ]);
} else {
    http_response_code(404);
    resa_print_meta([
        'title' => 'نویسنده یافت نشد',
        'description' => SITE_DEFAULT_DESCRIPTION,
        'url' => '/resume.html',
        'noindex' => true,
        'with_ids' => true,
    ]);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="assets/logo/favicon.svg" type="image/svg+xml">
<link rel="icon" href="assets/logo/favicon-96x96.png" type="image/png" sizes="96x96">
<link rel="shortcut icon" href="assets/logo/favicon.ico">
<link rel="apple-touch-icon" sizes="180x180" href="assets/logo/apple-touch-icon.png">
<link rel="manifest" href="assets/logo/site.webmanifest">
<meta name="theme-color" content="#171a1a">
<link href="assets/vendor/vazirmatn/Vazirmatn-font-face.css" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/articles.css">
<link rel="stylesheet" href="css/projects.css">
<link rel="stylesheet" href="css/author.css">
<?php if ($author): ?>
<?php
resa_print_json_ld([
    '@context' => 'https://schema.org',
    '@type' => 'ProfilePage',
    'mainEntity' => [
        '@type' => 'Person',
        'name' => $author['display_name'] ?: $author['username'],
        'image' => $author['avatar'] ? resa_abs_url($author['avatar']) : resa_abs_url(SITE_DEFAULT_OG_IMAGE),
        'description' => $author['bio_short'] ?: null,
        'jobTitle' => $author['job_title'] ?: null,
        'worksFor' => resa_organization_json_ld(),
    ],
]);
?>
<?php endif; ?>
</head>
<body>

<main class="articles-page author-page">

  <div class="author-loading" id="authorLoading">
    <div class="loading-spinner"></div>
    <p>در حال بارگذاری پروفایل نویسنده...</p>
  </div>

  <div class="author-not-found" id="authorNotFound" style="display:none;">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3"><circle cx="12" cy="8" r="3.4"/><path d="M5 20c0-3.6 3.1-6.4 7-6.4s7 2.8 7 6.4"/></svg>
    <h1>این نویسنده پیدا نشد</h1>
    <p>ممکن است حذف شده یا آدرس اشتباه باشد.</p>
    <a href="articles.html" class="pagination-btn">بازگشت به وبلاگ‌ها</a>
  </div>

  <div id="authorContent" style="display:none;" class="author-container">

    <div class="author-layout">

      <!-- ================= ستون سمت راست: پروفایل ثابت (Sticky Sidebar) ================= -->
      <aside class="author-sidebar">
        <div class="author-profile-card">
          <div class="author-profile-top">
            <div class="author-avatar-square">
              <img id="authorHeroAvatarImg" alt="" style="display:none;">
              <span id="authorHeroInitial"></span>
            </div>

            <div class="author-meta-right">
              <div class="author-name-wrap">
                <h1 class="author-name" id="authorHeroName"></h1>
              </div>

              <div class="author-stats-row">
                <div class="stat-col">
                  <span class="stat-count" id="authorTabArticlesCount">0</span>
                  <span class="stat-label">وبلاگ‌ها</span>
                </div>

                <div class="stat-col">
                  <span class="stat-count" id="authorTabProjectsCount">0</span>
                  <span class="stat-label">پروژه‌ها</span>
                </div>
              </div>
            </div>
          </div>

          <!-- آخرین فعالیت -->
          <div class="author-recent-activity" id="authorRecentActivity" style="display:none;">
            <span class="activity-dot"></span>
            <span id="authorRecentActivityText"></span>
          </div>

          <!-- بیوگرافی -->
          <div class="author-bio-section">
            <div class="author-role" id="authorHeroRole" style="display:none;"></div>
            <p class="author-bio" id="authorHeroBio" style="display:none;"></p>
          </div>

          <!-- نشان‌های دستاورد -->
          <div class="author-badges" id="authorBadges" style="display:none;"></div>

          <!-- شبکه های اجتماعی -->
          <div class="author-social-tags" id="authorSocialTags"></div>
        </div>
      </aside>

      <!-- ================= ستون سمت چپ: سرچ، تب‌ها و گرید محتوا ================= -->
      <section class="author-main-content">

        <!-- سرچ -->
        <div class="author-search-wrap">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/></svg>
          <input type="search" id="authorSearchInput" placeholder="جستجو...">
        </div>

        <!-- تب‌ها -->
        <div class="author-insta-tabs-wrapper">
          <div class="author-insta-tabs" id="authorTabs">
            <button type="button" class="author-tab is-active" id="tabBtnArticles" data-tab="articles">
              <span class="tab-text">وبلاگ‌ها</span>
            </button>
            <button type="button" class="author-tab" id="tabBtnProjects" data-tab="projects">
              <span class="tab-text">پروژه‌ها</span>
            </button>
            <div class="author-tab-indicator" id="tabIndicator"></div>
          </div>
        </div>

        <!-- گرید کارت‌ها -->
        <div class="author-pin-grid" id="authorPinGrid">
          <div class="list-skel"></div>
          <div class="list-skel"></div>
        </div>

        <div class="author-pin-empty" id="authorPinEmpty" style="display:none;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3"><path d="M6 3.5h9L19 8v12.5a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1Z"/><path d="M9 12h6M9 16h6"/></svg>
          <p id="authorPinEmptyText">هنوز موردی ثبت نشده است.</p>
        </div>

        <div class="pin-load-more-wrap">
          <button class="pagination-btn" id="authorPinMore" style="display:none;">نمایش بیشتر</button>
        </div>

      </section>

    </div>

  </div>

</main>

<nav class="nav-pill" aria-label="ناوبری اصلی">
  <a class="nav-item" data-color="#00ffff" href="index.html">
      <span class="nav-ring"></span>
      <span class="nav-content"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v9a1 1 0 0 0 1 1H9a1 1 0 0 0 1-1v-4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v4a1 1 0 0 0 1 1h2.5a1 1 0 0 0 1-1v-9"/></svg>
      <span class="nav-label">خانه</span></span>
    </a>
    <a class="nav-item" data-color="#7bfeff" href="about.html">
      <span class="nav-ring"></span>
      <span class="nav-content"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.4"/><path d="M5 20c0-3.6 3.1-6.4 7-6.4s7 2.8 7 6.4"/></svg>
      <span class="nav-label">درباره ما</span></span>
    </a>
    <a class="nav-item" data-color="#0c8d8d" href="projects.html">
      <span class="nav-ring"></span>
      <span class="nav-content"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5.5" width="7" height="7" rx="1.2"/><rect x="13.5" y="5.5" width="7" height="7" rx="1.2"/><rect x="3.5" y="15.5" width="7" height="7" rx="1.2"/><rect x="13.5" y="15.5" width="7" height="7" rx="1.2"/></svg>
      <span class="nav-label">پروژه‌ها</span></span>
    </a>
    <a class="nav-item" data-color="#b9feff" href="articles.html">
      <span class="nav-ring"></span>
      <span class="nav-content"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3.5h9L19 8v12.5a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1Z"/><path d="M9 12h6M9 16h6"/></svg>
      <span class="nav-label">وبلاگ‌ها</span></span>
    </a>
    <a class="nav-item" data-color="#125454" href="contact.html">
      <span class="nav-ring"></span>
      <span class="nav-content"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5.5" width="17" height="13" rx="1.6"/><path d="M4.5 7 12 13l7.5-6"/></svg>
      <span class="nav-label">تماس با ما</span></span>
    </a>
  </nav>

<footer class="site-footer">
  <div class="footer-grid">
    <div class="footer-brand">
      <img class="logo-mark" src="assets/logo/logo-filled-cyan.svg" alt="نشان رسا تیم">
      <h3>رسا تیم</h3>
      <p>جایی که رویاها به شکل روشن و رسا بیان می‌شوند.</p>
    </div>
    <div class="footer-col">
      <h4>صفحات</h4>
      <ul>
        <li><a href="index.html">خانه</a></li>
        <li><a href="about.html">درباره ما</a></li>
        <li><a href="projects.html">پروژه‌ها</a></li>
        <li><a href="articles.html">وبلاگ‌ها</a></li>
        <li><a href="contact.html">تماس با ما</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>ارتباط</h4>
      <ul>
        <li><a href="mailto:info@rasateams.ir">info@rasateams.ir</a></li>
        <li><a href="#">اینستاگرام</a></li>
        <li><a href="#">تلگرام</a></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <span>© ۱۴۰۴ رسا تیم</span>
    <span>ساخته‌شده با ❤ برای رویاهای رسا</span>
  </div>
</footer>

<script src="js/main.js"></script>
<script src="js/author.js"></script>
</body>
</html>