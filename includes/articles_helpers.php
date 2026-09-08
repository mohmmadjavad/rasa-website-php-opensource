<?php
/**
 * includes/articles_helpers.php
 * توابع مشترک بخش وبلاگ‌ها: اسلاگ‌سازی، محاسبه‌ی زمان مطالعه، آپلود تصویر و ...
 */

if (!defined('RESA_APP')) {
    http_response_code(403);
    exit('Access denied.');
}

/**
 * ساخت اسلاگ از متن فارسی/انگلیسی. حروف فارسی حفظ می‌شوند، فاصله‌ها به خط‌تیره
 * تبدیل می‌شوند و کاراکترهای غیرمجاز حذف می‌شوند.
 */
function resa_slugify(string $text): string
{
    $text = trim($text);
    $text = str_replace(['ي', 'ك', '‌'], ['ی', 'ک', '-'], $text);
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\p{Arabic}a-z0-9\-]+/u', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    $text = trim($text, '-');
    if ($text === '') {
        $text = 'item-' . substr(bin2hex(random_bytes(3)), 0, 6);
    }
    return $text;
}

/**
 * تضمین یکتا بودن اسلاگ در جدول مشخص‌شده (با افزودن پسوند عددی در صورت تکراری بودن)
 */
function resa_unique_slug(PDO $pdo, string $table, string $baseSlug, ?int $excludeId = null): string
{
    $slug = $baseSlug;
    $i = 2;
    while (true) {
        if ($excludeId !== null) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $excludeId]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE slug = ?");
            $stmt->execute([$slug]);
        }
        if ((int)$stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $i;
        $i++;
    }
}

/**
 * محاسبه‌ی تقریبی زمان مطالعه (به دقیقه) بر اساس تعداد کلمات محتوا (فارسی ~ ۱۸۰ کلمه در دقیقه)
 */
function resa_calc_reading_time(string $htmlContent): int
{
    $text = trim(strip_tags($htmlContent));
    if ($text === '') return 1;
    $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $count = count($words);
    $minutes = (int)ceil($count / 180);
    return max(1, $minutes);
}

/**
 * آپلود یک فایل تصویر و بازگرداندن مسیر نسبی ذخیره‌شده (نسبت به ریشه سایت)
 * $relativeDir مسیر پوشه‌ی مقصد زیر assets/uploads/ است، مثلاً 'articles/covers'، 'admins'، 'categories'
 */
function resa_upload_image(array $file, string $relativeDir): array
{
    $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $maxSize = 6 * 1024 * 1024; // 6MB

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'خطا در آپلود فایل.'];
    }
    if ($file['size'] > $maxSize) {
        return ['ok' => false, 'message' => 'حجم تصویر نباید بیشتر از ۶ مگابایت باشد.'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return ['ok' => false, 'message' => 'فرمت تصویر مجاز نیست. فقط jpg, png, webp, gif.'];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowedMime, true)) {
        return ['ok' => false, 'message' => 'نوع فایل تصویر معتبر نیست.'];
    }

    $relativeDir = trim(str_replace('\\', '/', $relativeDir), '/');
    $baseDir = dirname(__DIR__) . '/assets/uploads/' . $relativeDir;
    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0755, true);
    }

    // گیف‌های متحرک را بدون تبدیل ذخیره می‌کنیم تا انیمیشن از بین نرود.
    $isAnimatedGif = ($ext === 'gif') && resa_is_animated_gif($file['tmp_name']);

    if ($isAnimatedGif) {
        $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.gif';
        $destPath = $baseDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return ['ok' => false, 'message' => 'ذخیره‌سازی فایل تصویر با خطا مواجه شد.'];
        }
        $relative = 'assets/uploads/' . $relativeDir . '/' . $filename;
        return ['ok' => true, 'path' => $relative];
    }

    // در غیر این صورت، تصویر به فرمت WebP تبدیل می‌شود (حجم کمتر، کیفیت بالا).
    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.webp';
    $destPath = $baseDir . '/' . $filename;

    $converted = function_exists('imagewebp') ? resa_convert_to_webp($file['tmp_name'], $mime, $destPath) : false;

    if (!$converted) {
        // اگر تبدیل به WebP ممکن نبود (کتابخانه GD موجود نیست)، فایل اصلی بدون تغییر ذخیره می‌شود.
        $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
        $destPath = $baseDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return ['ok' => false, 'message' => 'ذخیره‌سازی فایل تصویر با خطا مواجه شد.'];
        }
    }

    $relative = 'assets/uploads/' . $relativeDir . '/' . $filename;
    return ['ok' => true, 'path' => $relative];
}

/**
 * بررسی می‌کند که آیا فایل گیف متحرک است یا خیر (با شمارش فریم‌های تصویر).
 */
function resa_is_animated_gif(string $path): bool
{
    $raw = @file_get_contents($path);
    if ($raw === false) return false;
    return substr_count($raw, "\x00\x21\xF9\x04") > 1;
}

/**
 * تبدیل یک فایل تصویری (jpg/png/gif ثابت/webp) به فرمت WebP با استفاده از GD.
 * در صورت موفقیت true و در غیر این صورت false برمی‌گرداند.
 */
function resa_convert_to_webp(string $srcPath, string $mime, string $destPath): bool
{
    try {
        switch ($mime) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($srcPath);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($srcPath);
                break;
            case 'image/gif':
                $image = @imagecreatefromgif($srcPath);
                break;
            case 'image/webp':
                $image = @imagecreatefromwebp($srcPath);
                break;
            default:
                $image = false;
        }

        if (!$image) {
            return false;
        }

        // حفظ شفافیت برای تصاویر PNG/GIF/WebP
        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $ok = imagewebp($image, $destPath, 82);
        imagedestroy($image);

        return $ok && is_file($destPath);
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * آپلود فایل ویدیو یا صدا (برای پوستر ویدیویی پروژه یا افزودن ویدیو/آهنگ داخل متن)
 * $kind یکی از 'video' یا 'audio' است.
 */
function resa_upload_media(array $file, string $relativeDir, string $kind): array
{
    $allowed = [
        'video' => [
            'ext'  => ['mp4', 'webm', 'ogg', 'mov'],
            'mime' => ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'],
            'max'  => 60 * 1024 * 1024, // 60MB
        ],
        'audio' => [
            'ext'  => ['mp3', 'wav', 'ogg', 'm4a'],
            'mime' => ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/x-wav', 'audio/mp4', 'audio/x-m4a'],
            'max'  => 25 * 1024 * 1024, // 25MB
        ],
    ];
    if (!isset($allowed[$kind])) {
        return ['ok' => false, 'message' => 'نوع فایل نامعتبر است.'];
    }
    $rule = $allowed[$kind];

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'خطا در آپلود فایل.'];
    }
    if ($file['size'] > $rule['max']) {
        $mb = (int)($rule['max'] / 1024 / 1024);
        return ['ok' => false, 'message' => "حجم فایل نباید بیشتر از {$mb} مگابایت باشد."];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $rule['ext'], true)) {
        return ['ok' => false, 'message' => 'فرمت فایل مجاز نیست.'];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $rule['mime'], true)) {
        return ['ok' => false, 'message' => 'نوع فایل معتبر نیست.'];
    }

    $relativeDir = trim(str_replace('\\', '/', $relativeDir), '/');
    $baseDir = dirname(__DIR__) . '/assets/uploads/' . $relativeDir;
    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0755, true);
    }
    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    $destPath = $baseDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['ok' => false, 'message' => 'ذخیره‌سازی فایل با خطا مواجه شد.'];
    }

    $relative = 'assets/uploads/' . $relativeDir . '/' . $filename;
    return ['ok' => true, 'path' => $relative];
}

/* ==================================================================
   ضدعفونی‌کننده‌ی HTML محتوای وبلاگ‌ها/پروژه‌ها (ضد XSS ذخیره‌شده)
   -------------------------------------------------------------
   محتوای ادیتور RTE به‌صورت HTML خام از سمت کاربر (ادمین) دریافت
   می‌شود و روی صفحه‌ی عمومی سایت مستقیماً با innerHTML نمایش داده
   می‌شود. بدون این فیلتر، هر ادمینی (حتی ادمین با دسترسی محدود، یا
   یک حساب ادمین هک‌شده) می‌تواند کد جاوااسکریپت مخرب را داخل وبلاگ
   تزریق کند که برای همه‌ی بازدیدکنندگان سایت اجرا می‌شود. این تابع
   فقط تگ‌ها و ویژگی‌های موردنیاز ادیتور را مجاز می‌کند و بقیه را حذف
   می‌کند.
   ================================================================== */

/**
 * لیست تگ‌های مجاز و ویژگی‌های مجاز هر تگ برای محتوای غنی (rich text).
 */
function resa_html_allowlist(): array
{
    return [
        'p' => ['style'], 'br' => [], 'hr' => [],
        'b' => [], 'strong' => [], 'i' => [], 'em' => [], 'u' => [], 's' => [], 'strike' => [],
        'span' => ['style'], 'div' => ['style'],
        'h1' => ['style'], 'h2' => ['style'], 'h3' => ['style'], 'h4' => ['style'], 'h5' => ['style'], 'h6' => ['style'],
        'ul' => ['style'], 'ol' => ['style'], 'li' => ['style'],
        'blockquote' => ['style'], 'code' => [], 'pre' => [],
        'a' => ['href', 'target', 'rel', 'style'],
        'img' => ['src', 'alt', 'style', 'width', 'height', 'loading', 'data-src'],
        'video' => ['src', 'controls', 'style', 'poster', 'data-src'],
        'audio' => ['src', 'controls', 'style', 'data-src'],
        'iframe' => ['src', 'allowfullscreen', 'loading', 'style', 'frameborder'],
        'table' => ['style'], 'thead' => [], 'tbody' => [], 'tr' => [], 'td' => ['style', 'colspan', 'rowspan'], 'th' => ['style', 'colspan', 'rowspan'],
    ];
}

/**
 * بررسی می‌کند که مقدار href/src برای لینک یا تصویر امن است (نه javascript:، نه data: مگر تصویر معتبر).
 */
function resa_is_safe_url(string $url): bool
{
    $url = trim($url);
    if ($url === '') return false;
    if (preg_match('/^\s*(javascript|vbscript|data)\s*:/i', $url)) return false;
    if (preg_match('#^(https?:)?//#i', $url)) return true;
    if (preg_match('/^(mailto|tel):/i', $url)) return true;
    if ($url[0] === '/' || $url[0] === '#' || $url[0] === '.') return true;
    if (!preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url)) return true;
    return false;
}

/**
 * مقدار ویژگی style را از الگوهای خطرناک (expression, url جاوااسکریپتی و ...) پاک می‌کند.
 */
function resa_sanitize_style(string $style): string
{
    if (preg_match('/expression\s*\(|javascript\s*:|behaviour\s*:|-moz-binding|@import/i', $style)) {
        return '';
    }
    if (preg_match_all('/url\s*\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', $style, $m)) {
        foreach ($m[1] as $u) {
            if (!resa_is_safe_url($u)) return '';
        }
    }
    return $style;
}

/**
 * src یک iframe فقط برای embed های یوتیوب/آپارات مجاز است (همان چیزی که ادیتور تولید می‌کند).
 */
function resa_is_allowed_iframe_src(string $src): bool
{
    $host = parse_url($src, PHP_URL_HOST);
    if (!$host) return false;
    $host = strtolower($host);
    $allowedHosts = ['www.youtube.com', 'youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com', 'www.aparat.com', 'aparat.com'];
    foreach ($allowedHosts as $h) {
        if ($host === $h) return true;
    }
    return false;
}

/**
 * یک گره DOM را به‌صورت بازگشتی پالایش می‌کند: تگ‌های غیرمجاز را کامل حذف
 * می‌کند (نه فقط باز کردن)، و از تگ‌های مجاز فقط ویژگی‌های امن را نگه می‌دارد.
 */
function resa_sanitize_dom_node(DOMNode $node, array $allowlist): void
{
    $children = [];
    foreach ($node->childNodes as $child) {
        $children[] = $child;
    }

    foreach ($children as $child) {
        if ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
            continue;
        }
        if ($child->nodeType === XML_COMMENT_NODE) {
            $node->removeChild($child);
            continue;
        }
        if ($child->nodeType !== XML_ELEMENT_NODE) {
            $node->removeChild($child);
            continue;
        }

        /** @var DOMElement $child */
        $tag = strtolower($child->tagName);

        if (!isset($allowlist[$tag])) {
            // تگ غیرمجاز (script, style, form, object, embed, link, meta, base, input, ...)
            // به‌طور کامل همراه با محتوایش حذف می‌شود تا هیچ کد اجرایی باقی نماند.
            $node->removeChild($child);
            continue;
        }

        $allowedAttrs = $allowlist[$tag];
        $attrsToRemove = [];
        foreach ($child->attributes as $attr) {
            $attrName = strtolower($attr->name);
            if (strpos($attrName, 'on') === 0) {
                $attrsToRemove[] = $attr->name;
                continue;
            }
            if (!in_array($attrName, $allowedAttrs, true)) {
                $attrsToRemove[] = $attr->name;
                continue;
            }
            if (($attrName === 'href' || $attrName === 'src' || $attrName === 'poster') && !resa_is_safe_url($attr->value)) {
                $attrsToRemove[] = $attr->name;
                continue;
            }
            if ($tag === 'iframe' && $attrName === 'src' && !resa_is_allowed_iframe_src($attr->value)) {
                $attrsToRemove[] = $attr->name;
                continue;
            }
            if ($attrName === 'style') {
                $safeStyle = resa_sanitize_style($attr->value);
                if ($safeStyle === '') {
                    $attrsToRemove[] = $attr->name;
                } else {
                    $attr->value = htmlspecialchars($safeStyle, ENT_QUOTES, 'UTF-8');
                }
            }
        }
        foreach ($attrsToRemove as $a) {
            $child->removeAttribute($a);
        }
        if ($tag === 'a') {
            if ($child->getAttribute('target') === '_blank') {
                $child->setAttribute('rel', 'noopener noreferrer');
            }
        }

        resa_sanitize_dom_node($child, $allowlist);
    }
}

/**
 * ورودی: HTML خام از ادیتور. خروجی: نسخه‌ی پالایش‌شده که فقط شامل تگ‌ها/ویژگی‌های
 * مجاز است — بدون script، بدون هندلر رویداد (onerror, onclick, ...)، بدون
 * javascript:/data: در لینک‌ها، و بدون iframe به مقصد دلخواه.
 */
function resa_sanitize_html(string $html): string
{
    $html = trim($html);
    if ($html === '') return '';

    $dom = new DOMDocument('1.0', 'UTF-8');
    $prevErrors = libxml_use_internal_errors(true);
    $wrapped = '<?xml encoding="UTF-8"><div id="resa-sanitize-root">' . $html . '</div>';
    $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($prevErrors);

    $root = $dom->getElementById('resa-sanitize-root');
    if (!$root) {
        return '';
    }

    resa_sanitize_dom_node($root, resa_html_allowlist());

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }
    return trim($out);
}

/**
 * حذف فایل تصویر آپلود شده (اگر مسیر داخل پوشه آپلودهای وبلاگ‌ها باشد)
 */
function resa_delete_uploaded_image(?string $relativePath): void
{
    if (!$relativePath) return;
    if (strpos($relativePath, 'assets/uploads/') !== 0) return;
    $fullPath = dirname(__DIR__) . '/' . $relativePath;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}
