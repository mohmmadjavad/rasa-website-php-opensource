<?php
/**
 * project.php (از طریق rewrite داخلی project.html به این فایل می‌رسد)
 * رندر سمت‌سرور متاتگ‌ها/OG/JSON-LD برای هر پروژه.
 */
define('RESA_APP', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/seo-helpers.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$previewToken = trim((string)($_GET['preview_token'] ?? ''));
$project = null;

if ($slug !== '') {
    try {
        $pdo = resa_db();
        $stmt = $pdo->prepare("
            SELECT title, slug, excerpt, cover_type, cover_image, meta_title, meta_description,
                   published_at, updated_at
            FROM projects
            WHERE slug = ? AND status = 'published' AND (published_at IS NULL OR published_at <= NOW())
            LIMIT 1
        ");
        $stmt->execute([$slug]);
        $project = $stmt->fetch();
    } catch (Throwable $e) {
        $project = null;
    }
}

if ($project) {
    resa_print_meta([
        'title' => $project['meta_title'] ?: $project['title'],
        'description' => $project['meta_description'] ?: $project['excerpt'],
        'url' => '/project.html?slug=' . rawurlencode($project['slug']),
        'image' => ($project['cover_type'] === 'image') ? $project['cover_image'] : null,
        'type' => 'article',
        'modified_time' => $project['updated_at'] ? date('c', strtotime($project['updated_at'])) : null,
        'with_ids' => true,
    ]);
} elseif ($previewToken !== '') {
    resa_print_meta([
        'title' => 'پیش‌نمایش پروژه',
        'description' => SITE_DEFAULT_DESCRIPTION,
        'url' => '/project.html',
        'noindex' => true,
        'with_ids' => true,
    ]);
} else {
    http_response_code(404);
    resa_print_meta([
        'title' => 'پروژه یافت نشد',
        'description' => SITE_DEFAULT_DESCRIPTION,
        'url' => '/project.html',
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
<link rel="stylesheet" href="css/project.css">
<?php if ($project): ?>
<?php
resa_print_json_ld([
    '@context' => 'https://schema.org',
    '@type' => 'CreativeWork',
    'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => resa_abs_url('/project.html?slug=' . rawurlencode($project['slug']))],
    'name' => $project['meta_title'] ?: $project['title'],
    'description' => resa_seo_truncate((string)($project['meta_description'] ?: $project['excerpt']), 300),
    'image' => ($project['cover_type'] === 'image' && $project['cover_image']) ? [resa_abs_url($project['cover_image'])] : [resa_abs_url(SITE_DEFAULT_OG_IMAGE)],
    'dateModified' => $project['updated_at'] ? date('c', strtotime($project['updated_at'])) : null,
    'creator' => resa_organization_json_ld(),
]);
resa_print_json_ld(resa_breadcrumb_json_ld([
    'خانه' => '/',
    'پروژه‌ها' => '/projects.html',
    ($project['title']) => null,
]));
?>
<?php endif; ?>
</head>
<body>

<div class="reading-progress"><div class="reading-progress-bar" id="readingProgressBar"></div></div>

<main class="project-page" id="projectPage">

  <div class="project-loading" id="projectLoading">
    <div class="loading-spinner"></div>
    <p>در حال بارگذاری پروژه...</p>
  </div>

  <div class="project-not-found" id="projectNotFound" style="display:none;">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3"><rect x="3.5" y="5.5" width="7" height="7" rx="1.2"/><rect x="13.5" y="5.5" width="7" height="7" rx="1.2"/><rect x="3.5" y="15.5" width="7" height="7" rx="1.2"/><rect x="13.5" y="15.5" width="7" height="7" rx="1.2"/></svg>
    <h1>این پروژه پیدا نشد</h1>
    <p>ممکن است حذف شده یا آدرس آن تغییر کرده باشد.</p>
    <a href="projects.html" class="project-btn">بازگشت به پروژه‌ها</a>
  </div>

  <article class="project-article" id="projectContent" style="display:none;">

    <!-- ================= هیرو سینمایی ================= -->
    <header class="project-hero" id="projectHero">
      <div class="project-hero-media" id="projectHeroMedia">
        <img id="projectHeroImg" src="" alt="" style="display:none;">
        <video id="projectHeroVideo" autoplay muted loop playsinline style="display:none;"></video>
      </div>
      <div class="project-hero-scrim"></div>

      <div class="project-hero-topbar">
        <a href="projects.html" class="project-back">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l-7 7 7 7M2 12h20"/></svg>
          پروژه‌ها
        </a>
        <div class="project-hero-share">
          <button id="shareNativeBtn" class="hero-icon-btn" title="اشتراک‌گذاری">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="2.6"/><circle cx="6" cy="12" r="2.6"/><circle cx="18" cy="19" r="2.6"/><path d="M8.3 10.7 15.7 6.3M8.3 13.3l7.4 4.4"/></svg>
          </button>
          <a id="shareTelegram" class="hero-icon-btn" target="_blank" rel="noopener" title="تلگرام">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 17-7-6 17-4.5-6.5L3 11Z"/><path d="m9.5 14.5 8.5-9.5"/></svg>
          </a>
          <button id="shareCopyBtn" class="hero-icon-btn" title="کپی لینک">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
          </button>
        </div>
      </div>

      <div class="project-hero-cue" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
      </div>
    </header>

    <!-- ================= معرفی پروژه — چیدمان نامتقارن ================= -->
    <section class="project-intro">
      <div class="project-intro-left">
        <a href="#" class="project-hero-eyebrow" id="projectCatBadge" style="display:none;">
          <span id="projectCatBadgeName"></span>
        </a>
        <h1 id="projectTitle"></h1>
      </div>
      <div class="project-intro-right">
        <p class="project-intro-excerpt" id="projectExcerpt"></p>
        <ul class="project-stat-list">
          <li>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5.5" width="17" height="15" rx="2"/><path d="M3.5 9.5h17M8 3.5v4M16 3.5v4"/></svg>
            <span id="projectDate"></span>
          </li>
          <li>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            <span id="projectViews"></span>
          </li>
          <li id="projectTeamCountItem" style="display:none;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M2.8 19c0-3.4 2.8-6 6.2-6s6.2 2.6 6.2 6"/><circle cx="17.5" cy="8.5" r="2.6"/><path d="M15.5 13.2c2.7.4 4.7 2.6 4.7 5.3"/></svg>
            <span id="projectTeamCount"></span>
          </li>
        </ul>
      </div>
    </section>

    <!-- ================= تیم پروژه — ردیف چرخان ================= -->
    <section class="project-team-section" id="projectTeamSection" style="display:none;">
      <div class="film-sprocket-strip" aria-hidden="true"></div>
      <div class="section-eyebrow">تیتراژ</div>
      <h2>عوامل این پروژه</h2>
      <div class="project-team-marquee">
        <div class="project-team-row" id="projectTeamGrid"></div>
      </div>
      <div class="film-sprocket-strip" aria-hidden="true"></div>
    </section>

    <!-- ================= ریل فصل‌ها — شمارشگر قاب سینمایی ================= -->
    <nav class="chapter-rail" id="chapterRail" aria-label="فصل‌های پروژه" style="display:none;">
      <div class="chapter-rail-frame" id="chapterRailFrame">۰۰</div>
      <ol class="chapter-rail-list" id="chapterRailList"></ol>
      <div class="chapter-rail-sprocket" aria-hidden="true"></div>
    </nav>

    <!-- ================= محتوا ================= -->
    <section class="project-prose-section">
      <div class="project-prose" id="projectProse"></div>
    </section>

    <!-- ================= پروژه‌های مرتبط ================= -->
    <section class="project-related-section" id="relatedSection" style="display:none;">
      <div class="project-related-head">
        <div class="section-eyebrow">ادامه بدید</div>
        <h2>پروژه‌های مرتبط</h2>
      </div>
      <div class="project-related-scroller" id="relatedGrid"></div>
    </section>

  </article>
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
  <a class="nav-item is-active" data-color="#0c8d8d" href="projects.html">
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
      <p>جایی که رویاها به شکل روشن و رسا بیان می‌شوند. این متن نمونه است و باید با معرفی واقعی برند جایگزین شود.</p>
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
<script src="js/project.js"></script>
</body>
</html>