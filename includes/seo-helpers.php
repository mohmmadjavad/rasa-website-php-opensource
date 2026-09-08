<?php
/**
 * includes/seo-helpers.php
 * تنظیمات و توابع مشترک سئو: نام سایت، دامنه، ساخت متاتگ‌ها، Open Graph،
 * Twitter Card و JSON-LD برای صفحات پویا (وبلاگ/پروژه/نویسنده).
 *
 * این فایل عمداً مستقل از احراز هویت است تا هم در صفحات عمومی (بدون نیاز
 * به RESA_APP کامل بودن سشن) و هم در sitemap.php/robots قابل استفاده باشد.
 */

if (!defined('RESA_APP')) {
    http_response_code(403);
    exit('Access denied.');
}

// ---------- هویت سایت ----------
define('SITE_NAME', 'رسا تیم');
define('SITE_NAME_EN', 'Resa Team');
// دامنه‌ی اصلی سایت — همیشه بدون اسلش انتهایی
define('SITE_DOMAIN', 'rasateams.ir');
define('SITE_URL', 'https://' . SITE_DOMAIN);
define('SITE_DEFAULT_DESCRIPTION', 'رسا تیم؛ رسانه و تیم تولید محتوا و پروژه‌های دانشجویی — وبلاگ‌ها، پروژه‌ها و روایت‌های رسا.');
define('SITE_LOCALE', 'fa_IR');
// مسیر نسبی تصویر پیش‌فرض برای اشتراک‌گذاری (وقتی صفحه‌ای عکس اختصاصی ندارد)
define('SITE_DEFAULT_OG_IMAGE', '/assets/logo/web-app-manifest-512x512.png');

/**
 * تبدیل مسیر نسبی (یا خالی) به URL کامل و مطلق روی دامنه‌ی سایت.
 * اگر ورودی از قبل یک URL کامل (http/https) باشد، همان‌طور برگردانده می‌شود.
 */
function resa_abs_url(?string $path): string
{
    $path = trim((string)$path);
    if ($path === '') {
        return SITE_URL . '/';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return SITE_URL . '/' . ltrim($path, '/');
}

/**
 * کوتاه‌سازی امن یک رشته (بر اساس تعداد کاراکتر) برای متا-دیسکریپشن،
 * بدون بریدن وسط یک کلمه در صورت امکان.
 */
function resa_seo_truncate(string $text, int $max = 160): string
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    $cut = mb_substr($text, 0, $max);
    $lastSpace = mb_strrpos($cut, ' ');
    if ($lastSpace !== false && $lastSpace > (int)($max * 0.6)) {
        $cut = mb_substr($cut, 0, $lastSpace);
    }
    return rtrim($cut, " \t\n\r\0\x0B،,.") . '…';
}

/**
 * چاپ بلاک کامل متاتگ‌های سئو + Open Graph + Twitter Card برای یک صفحه.
 *
 * $opts keys:
 *   title        (بدون پسوند نام سایت — خودش اضافه می‌شود)
 *   description
 *   url          مسیر نسبی صفحه‌ی فعلی (مثلاً 'article.html?slug=...')
 *   image        مسیر نسبی یا کامل تصویر (اختیاری، پیش‌فرض تصویر سایت)
 *   type         og:type — 'website' یا 'article' (پیش‌فرض website)
 *   noindex      bool — اگر true باشد noindex,follow چاپ می‌شود
 *   published_time / modified_time (اختیاری برای og:type=article)
 *   author_name  (اختیاری برای og:type=article)
 */
function resa_print_meta(array $opts): void
{
    $title = trim((string)($opts['title'] ?? ''));
    $fullTitle = $title !== '' ? ($title . ' | ' . SITE_NAME) : SITE_NAME . ' | ' . SITE_DEFAULT_DESCRIPTION;
    $description = resa_seo_truncate((string)($opts['description'] ?? SITE_DEFAULT_DESCRIPTION), 300);
    $url = resa_abs_url($opts['url'] ?? '');
    $image = resa_abs_url($opts['image'] ?? SITE_DEFAULT_OG_IMAGE);
    $type = $opts['type'] ?? 'website';
    $noindex = !empty($opts['noindex']);

    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    // اگر صفحه بعداً با جاوااسکریپت محتوا را به‌روز می‌کند (حالت پیش‌نمایش)،
    // با with_ids=true همین تگ‌ها آی‌دی می‌گیرند تا اسکریپت مقدار را جایگزین
    // کند، نه اینکه تگ تکراری بسازد.
    $withId = !empty($opts['with_ids']);
    $idAttr = fn($id) => $withId ? ' id="' . $id . '"' : '';

    echo '<title' . $idAttr('pageTitle') . '>' . $e($fullTitle) . "</title>\n";
    echo '<meta name="description"' . $idAttr('pageDescription') . ' content="' . $e($description) . "\">\n";
    echo '<meta name="robots" content="' . ($noindex ? 'noindex,follow' : 'index,follow') . "\">\n";
    echo '<link rel="canonical" href="' . $e($url) . "\">\n";

    echo '<meta property="og:site_name" content="' . $e(SITE_NAME) . "\">\n";
    echo '<meta property="og:locale" content="' . $e(SITE_LOCALE) . "\">\n";
    echo '<meta property="og:type" content="' . $e($type) . "\">\n";
    echo '<meta property="og:title"' . $idAttr('ogTitle') . ' content="' . $e($title !== '' ? $title : SITE_NAME) . "\">\n";
    echo '<meta property="og:description"' . $idAttr('ogDescription') . ' content="' . $e($description) . "\">\n";
    echo '<meta property="og:url" content="' . $e($url) . "\">\n";
    echo '<meta property="og:image"' . $idAttr('ogImage') . ' content="' . $e($image) . "\">\n";
    echo '<meta property="og:image:secure_url" content="' . $e($image) . "\">\n";

    if ($type === 'article') {
        if (!empty($opts['published_time'])) {
            echo '<meta property="article:published_time" content="' . $e($opts['published_time']) . "\">\n";
        }
        if (!empty($opts['modified_time'])) {
            echo '<meta property="article:modified_time" content="' . $e($opts['modified_time']) . "\">\n";
        }
        if (!empty($opts['author_name'])) {
            echo '<meta property="article:author" content="' . $e($opts['author_name']) . "\">\n";
        }
    }

    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . $e($title !== '' ? $title : SITE_NAME) . "\">\n";
    echo '<meta name="twitter:description" content="' . $e($description) . "\">\n";
    echo '<meta name="twitter:image" content="' . $e($image) . "\">\n";
}

/**
 * چاپ یک بلاک JSON-LD (آرایه‌ی PHP را به schema.org تبدیل و echo می‌کند).
 */
function resa_print_json_ld(array $data): void
{
    $clean = resa_strip_nulls($data);
    echo '<script type="application/ld+json">'
        . json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . "</script>\n";
}

/**
 * حذف بازگشتی مقادیر null از یک آرایه (برای خروجی تمیز JSON-LD).
 */
function resa_strip_nulls(array $data): array
{
    $out = [];
    foreach ($data as $k => $v) {
        if ($v === null) continue;
        $out[$k] = is_array($v) ? resa_strip_nulls($v) : $v;
    }
    return $out;
}

/**
 * JSON-LD سازمانی مشترک (برای publisher در Article schema و برای صفحه‌ی اصلی).
 */
function resa_organization_json_ld(): array
{
    return [
        '@type' => 'Organization',
        'name' => SITE_NAME,
        'url' => SITE_URL . '/',
        'logo' => [
            '@type' => 'ImageObject',
            'url' => resa_abs_url('/assets/logo/web-app-manifest-512x512.png'),
        ],
    ];
}

/**
 * JSON-LD BreadcrumbList ساده از یک آرایه‌ی [نام => مسیر نسبی یا null برای آخرین آیتم].
 */
function resa_breadcrumb_json_ld(array $items): array
{
    $list = [];
    $pos = 1;
    foreach ($items as $name => $path) {
        $entry = [
            '@type' => 'ListItem',
            'position' => $pos++,
            'name' => $name,
        ];
        if ($path !== null) {
            $entry['item'] = resa_abs_url($path);
        }
        $list[] = $entry;
    }
    return [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $list,
    ];
}
