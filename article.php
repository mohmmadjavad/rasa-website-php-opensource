<?php
/**
 * article.php (از طریق rewrite داخلی article.html به این فایل می‌رسد؛
 * آدرس نمایشی برای کاربر و لینک‌ها همچنان article.html باقی می‌ماند)
 * رندر سمت‌سرور متاتگ‌ها/OG/JSON-LD برای هر وبلاگ، برای ایندکس بهتر
 * در گوگل و پیش‌نمایش صحیح لینک در تلگرام/توییتر/واتساپ.
 */
define('RESA_APP', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/seo-helpers.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$previewToken = trim((string)($_GET['preview_token'] ?? ''));
$article = null;

if ($slug !== '') {
    try {
        $pdo = resa_db();
        $stmt = $pdo->prepare("
            SELECT a.title, a.slug, a.excerpt, a.cover_image, a.meta_title, a.meta_description,
                   a.published_at, a.updated_at,
                   COALESCE(NULLIF(ad.display_name,''), ad.username, a.author) AS author_name
            FROM articles a
            LEFT JOIN admins ad ON ad.id = a.author_admin_id
            WHERE a.slug = ? AND a.status = 'published' AND a.published_at <= NOW()
            LIMIT 1
        ");
        $stmt->execute([$slug]);
        $article = $stmt->fetch();
    } catch (Throwable $e) {
        $article = null;
    }
}

if ($article) {
    resa_print_meta([
        'title' => $article['meta_title'] ?: $article['title'],
        'description' => $article['meta_description'] ?: $article['excerpt'],
        'url' => '/article.html?slug=' . rawurlencode($article['slug']),
        'image' => $article['cover_image'],
        'type' => 'article',
        'published_time' => $article['published_at'] ? date('c', strtotime($article['published_at'])) : null,
        'modified_time' => $article['updated_at'] ? date('c', strtotime($article['updated_at'])) : null,
        'author_name' => $article['author_name'],
        'with_ids' => true,
    ]);
} elseif ($previewToken !== '') {
    resa_print_meta([
        'title' => 'پیش‌نمایش وبلاگ',
        'description' => SITE_DEFAULT_DESCRIPTION,
        'url' => '/article.html',
        'noindex' => true,
        'with_ids' => true,
    ]);
} else {
    http_response_code(404);
    resa_print_meta([
        'title' => 'وبلاگ یافت نشد',
        'description' => SITE_DEFAULT_DESCRIPTION,
        'url' => '/article.html',
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
<link rel="stylesheet" href="css/article.css">
<?php if ($article): ?>
<?php
resa_print_json_ld([
    '@context' => 'https://schema.org',
    '@type' => 'BlogPosting',
    'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => resa_abs_url('/article.html?slug=' . rawurlencode($article['slug']))],
    'headline' => $article['meta_title'] ?: $article['title'],
    'description' => resa_seo_truncate((string)($article['meta_description'] ?: $article['excerpt']), 300),
    'image' => $article['cover_image'] ? [resa_abs_url($article['cover_image'])] : [resa_abs_url(SITE_DEFAULT_OG_IMAGE)],
    'datePublished' => $article['published_at'] ? date('c', strtotime($article['published_at'])) : null,
    'dateModified' => $article['updated_at'] ? date('c', strtotime($article['updated_at'])) : null,
    'author' => ['@type' => 'Person', 'name' => $article['author_name'] ?: SITE_NAME],
    'publisher' => resa_organization_json_ld(),
]);
resa_print_json_ld(resa_breadcrumb_json_ld([
    'خانه' => '/',
    'وبلاگ‌ها' => '/articles.html',
    ($article['title']) => null,
]));
?>
<?php endif; ?>
</head>
<body>

<div class="reading-progress"><div class="reading-progress-bar" id="readingProgressBar"></div></div>

<main class="article-page" id="articlePage">

  <div class="article-loading" id="articleLoading">
    <div class="loading-spinner"></div>
    <p>در حال بارگذاری وبلاگ...</p>
  </div>

  <div class="article-not-found" id="articleNotFound" style="display:none;">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3"><path d="M6 3.5h9L19 8v12.5a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1Z"/><path d="M9 12h6M9 16h6"/></svg>
    <h1>این وبلاگ پیدا نشد</h1>
    <p>ممکن است حذف شده یا آدرس آن تغییر کرده باشد.</p>
    <a href="articles.html" class="pagination-btn">بازگشت به وبلاگ‌ها</a>
  </div>

  <article class="article-content" id="articleContent" style="display:none;">

    <!-- ================= هیرو وبلاگ ================= -->
    <header class="article-hero" id="articleHero">
      <div class="article-hero-media">
        <img id="articleHeroImg" src="" alt="">
      </div>
      <div class="article-hero-overlay">
        <div class="article-hero-inner">
          <a href="articles.html" class="article-back">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l-7 7 7 7M2 12h20"/></svg>
            وبلاگ‌ها
          </a>
          <a href="#" class="article-cat-badge" id="articleCatBadge" style="display:none;">
            <span class="article-cat-badge-icon" id="articleCatBadgeIcon"></span>
            <span class="article-cat-badge-text">
              <strong id="articleCatBadgeName"></strong>
              <small id="articleCatBadgeDesc"></small>
            </span>
          </a>
          <h1 id="articleTitle"></h1>
          <div class="article-meta-row">
            <span class="article-author"><span class="author-avatar" id="authorAvatar"></span><span id="articleAuthor"></span></span>
            <span id="articleDate"></span>
            <span id="articleReadingTime"></span>
            <span id="articleViews"></span>
          </div>
        </div>
      </div>
    </header>

    <div class="article-body-grid">
      <div class="article-prose" id="articleProse"></div>

      <aside class="article-sidebar">
        <!-- ================= درباره نویسنده ================= -->
        <section class="author-box" id="authorBox" style="display:none;">
          <a class="author-box-avatar" id="authorBoxAvatarLink">
            <img id="authorBoxAvatar" alt="" style="display:none;">
            <span id="authorBoxInitial"></span>
          </a>
          <div class="author-box-info">
            <span class="author-box-label">درباره نویسنده</span>
            <a class="author-box-name-link" id="authorBoxNameLink"><h3 id="authorBoxName"></h3></a>
            <a class="author-box-bio-link" id="authorBoxBioLink"><p id="authorBoxBio" style="display:none;"></p></a>
            <div class="author-box-socials" id="authorBoxSocials"></div>
          </div>
          <a class="author-box-btn" id="authorBoxLink">وبلاگ‌ها این نویسنده</a>
        </section>

        <div class="categories-card" id="categoriesCard" style="display:none;">
          <h4>دسته‌بندی</h4>
          <a href="#" class="main-category-card" id="mainCategoryCard" style="display:none;">
            <span class="main-category-icon" id="mainCategoryIcon"></span>
            <span class="main-category-text">
              <strong id="mainCategoryName"></strong>
              <small id="mainCategoryDesc"></small>
            </span>
          </a>
          <div class="categories-cloud" id="articleCategoriesCloud"></div>
        </div>

        <div class="tags-card" id="tagsCard" style="display:none;">
          <h4>برچسب‌ها</h4>
          <div class="tags-cloud" id="articleTagsCloud"></div>
        </div>

        <div class="share-card">
          <h4>این وبلاگ را به اشتراک بگذار</h4>
          <div class="share-buttons">
            <button id="shareNativeBtn" title="اشتراک‌گذاری" class="share-btn">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="2.6"/><circle cx="6" cy="12" r="2.6"/><circle cx="18" cy="19" r="2.6"/><path d="M8.3 10.7 15.7 6.3M8.3 13.3l7.4 4.4"/></svg>
            </button>
            <a id="shareTelegram" class="share-btn" target="_blank" rel="noopener" title="تلگرام">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 17-7-6 17-4.5-6.5L3 11Z"/><path d="m9.5 14.5 8.5-9.5"/></svg>
            </a>
            <button id="shareCopyBtn" class="share-btn" title="کپی لینک">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
            </button>
          </div>
        </div>
      </aside>
    </div>
    
    <!-- ================= مطالب مرتبط ================= -->
    <section class="related-section" id="relatedSection" style="display:none;">
      <h2>مطالب مرتبط</h2>
      <div class="articles-grid" id="relatedGrid"></div>
    </section>

    <!-- ================= نظرات کاربران ================= -->
    <section class="comments-section" id="commentsSection">
      <h2>نظرات کاربران <span class="comments-count" id="commentsCount"></span></h2>

      <form class="comment-form" id="commentForm">
        <input type="text" name="website" class="comment-honeypot" tabindex="-1" autocomplete="off">
        <div class="comment-form-row">
          <input type="text" id="commentName" placeholder="نام شما" maxlength="100" required>
        </div>
        <textarea id="commentContent" placeholder="نظر خود را بنویسید..." rows="3" maxlength="2000" required></textarea>
        <div class="comment-form-row comment-form-captcha-row">
          <img id="commentCaptchaImg" class="comment-captcha-img" alt="کد امنیتی" title="برای تغییر کد کلیک کنید">
          <input type="text" id="commentCaptcha" placeholder="کد امنیتی را وارد کنید" maxlength="5" inputmode="numeric" required>
        </div>
        <div class="comment-form-actions">
          <span class="form-msg" id="commentFormMsg"></span>
          <button type="submit" class="pagination-btn" id="commentSubmitBtn">ارسال نظر</button>
        </div>
      </form>

      <div class="comments-list" id="commentsList">
        <div class="comments-empty" id="commentsEmpty" style="display:none;">هنوز نظری ثبت نشده؛ اولین نفری باشید که نظر می‌دهد!</div>
      </div>
    </section>

  </article>

</main>

<!-- ================= ناوبری ================= -->
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
  <a class="nav-item is-active" data-color="#b9feff" href="articles.html">
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

<!-- ================= مودال پاسخ به نظر (انتقال داده شده به انتهای body) ================= -->
<div id="replyModal" class="comment-modal" style="display: none;">
  <div class="comment-modal-content">
    <div class="comment-modal-header">
      <h3 id="modalReplyTitle">پاسخ به نظر</h3>
      <button type="button" id="closeReplyModalBtn" class="comment-modal-close">&times;</button>
    </div>
    <form class="comment-reply-form" id="modalReplyForm">
      <input type="text" name="website" class="comment-honeypot" tabindex="-1" autocomplete="off">
      <div class="comment-form-row">
        <input type="text" class="reply-name-input" id="modalReplyName" placeholder="نام شما" maxlength="100" required>
      </div>
      <textarea class="reply-content-input" id="modalReplyContent" placeholder="پاسخ خود را بنویسید..." rows="4" maxlength="2000" required></textarea>
      
      <div class="comment-form-row comment-form-captcha-row">
        <img class="comment-captcha-img reply-captcha-img" id="modalReplyCaptchaImg" alt="کد امنیتی" title="برای تغییر کد کلیک کنید">
        <input type="text" class="reply-captcha-input" id="modalReplyCaptchaInput" placeholder="کد امنیتی" maxlength="5" inputmode="numeric" required>
      </div>
      
      <div class="comment-form-actions">
        <span class="form-msg reply-form-msg" id="modalReplyMsg"></span>
        <div class="comment-reply-form-btns">
          <button type="button" class="comment-reply-cancel-btn" id="cancelReplyModalBtn">انصراف</button>
          <button type="submit" class="pagination-btn reply-submit-btn" id="modalReplySubmitBtn">ارسال پاسخ</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script src="js/main.js"></script>
<script src="js/article.js"></script>
</body>
</html>