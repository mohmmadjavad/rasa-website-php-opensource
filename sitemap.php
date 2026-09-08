<?php
/**
 * sitemap.php
 * نقشه‌ی سایت پویا — همیشه به‌روز: صفحات ثابت + همه‌ی وبلاگ‌ها و پروژه‌های
 * منتشرشده + تصویر شاخص هرکدام (image sitemap extension).
 * به هیچ ورودی کاربر وابسته نیست، فقط از دیتابیس می‌خواند.
 */

define('RESA_APP', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/seo-helpers.php';

header('Content-Type: application/xml; charset=utf-8');

function xml_esc(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function sitemap_date(?string $mysqlDateTime): string
{
    if (!$mysqlDateTime) {
        return date('c');
    }
    $ts = strtotime($mysqlDateTime);
    return $ts ? date('c', $ts) : date('c');
}

$pdo = resa_db();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

// ---------- صفحات ثابت ----------
$staticPages = [
    ['path' => '/',                'priority' => '1.0', 'changefreq' => 'daily'],
    ['path' => '/about.html',      'priority' => '0.7', 'changefreq' => 'monthly'],
    ['path' => '/contact.html',    'priority' => '0.6', 'changefreq' => 'monthly'],
    ['path' => '/articles.html',   'priority' => '0.9', 'changefreq' => 'daily'],
    ['path' => '/projects.html',   'priority' => '0.9', 'changefreq' => 'weekly'],
];
foreach ($staticPages as $p) {
    echo "  <url>\n";
    echo '    <loc>' . xml_esc(resa_abs_url($p['path'])) . "</loc>\n";
    echo '    <changefreq>' . $p['changefreq'] . "</changefreq>\n";
    echo '    <priority>' . $p['priority'] . "</priority>\n";
    echo "  </url>\n";
}

// ---------- وبلاگ‌ها منتشرشده ----------
try {
    $stmt = $pdo->query("
        SELECT slug, cover_image, title, updated_at, published_at
        FROM articles
        WHERE status = 'published' AND published_at <= NOW()
        ORDER BY published_at DESC
    ");
    while ($a = $stmt->fetch()) {
        echo "  <url>\n";
        echo '    <loc>' . xml_esc(resa_abs_url('/article.html?slug=' . rawurlencode($a['slug']))) . "</loc>\n";
        echo '    <lastmod>' . sitemap_date($a['updated_at'] ?: $a['published_at']) . "</lastmod>\n";
        echo "    <changefreq>weekly</changefreq>\n";
        echo "    <priority>0.8</priority>\n";
        if (!empty($a['cover_image'])) {
            echo "    <image:image>\n";
            echo '      <image:loc>' . xml_esc(resa_abs_url($a['cover_image'])) . "</image:loc>\n";
            echo '      <image:title>' . xml_esc($a['title']) . "</image:title>\n";
            echo "    </image:image>\n";
        }
        echo "  </url>\n";
    }
} catch (Throwable $e) {
    // اگر دیتابیس هنوز وبلاگی ندارد یا در دسترس نیست، فقط این بخش را رد کن
}

// ---------- پروژه‌های منتشرشده ----------
try {
    $stmt = $pdo->query("
        SELECT slug, cover_type, cover_image, title, updated_at, published_at
        FROM projects
        WHERE status = 'published' AND (published_at IS NULL OR published_at <= NOW())
        ORDER BY published_at DESC
    ");
    while ($p = $stmt->fetch()) {
        echo "  <url>\n";
        echo '    <loc>' . xml_esc(resa_abs_url('/project.html?slug=' . rawurlencode($p['slug']))) . "</loc>\n";
        echo '    <lastmod>' . sitemap_date($p['updated_at'] ?: $p['published_at']) . "</lastmod>\n";
        echo "    <changefreq>monthly</changefreq>\n";
        echo "    <priority>0.7</priority>\n";
        if ($p['cover_type'] === 'image' && !empty($p['cover_image'])) {
            echo "    <image:image>\n";
            echo '      <image:loc>' . xml_esc(resa_abs_url($p['cover_image'])) . "</image:loc>\n";
            echo '      <image:title>' . xml_esc($p['title']) . "</image:title>\n";
            echo "    </image:image>\n";
        }
        echo "  </url>\n";
    }
} catch (Throwable $e) {
    // نادیده گرفتن در صورت نبود جدول/داده
}

echo '</urlset>';
