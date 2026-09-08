<?php
/**
 * includes/spam-filter.php
 * فیلتر اسپم ساده و مبتنی بر قانون برای نظرات کاربران سایت.
 * بدون نیاز به سرویس بیرونی: بر اساس کلمات کلیدی مشکوک، تعداد لینک‌ها و
 * الگوهای متنی غیرعادی، نظرات مشکوک را تشخیص می‌دهد. اگر نظری مشکوک
 * تشخیص داده شود، مستقل از تنظیم «تایید خودکار»، در وضعیت «در انتظار
 * تایید» قرار می‌گیرد تا ادمین آن را بررسی کند.
 */

if (!defined('RESA_APP')) {
    http_response_code(403);
    exit('Access denied.');
}

function resa_detect_comment_spam(string $content, string $userName): ?string
{
    $bannedPhrases = [
        'وام فوری', 'بیت‌کوین رایگان', 'کلیک کنید اینجا', 'دارو بدون نسخه',
        'خرید فالوور', 'افزایش فالوور', 'بک لینک رایگان', 'کازینو آنلاین',
        'قرص لاغری', 'شرط‌بندی', 'دکتر جنسی', 'تضمینی درآمد',
        'viagra', 'casino', 'crypto giveaway', 'click here', 'loan approved',
        'backlink service', 'work from home', 'make money fast', 'free bitcoin',
        'follow for follow', 'seo service', 'ranking service',
    ];

    $haystack = mb_strtolower($content . ' ' . $userName);
    foreach ($bannedPhrases as $phrase) {
        if (mb_strpos($haystack, mb_strtolower($phrase)) !== false) {
            return 'شامل عبارت مشکوک به تبلیغ/اسپم: «' . $phrase . '»';
        }
    }

    $linkCount = preg_match_all('/(https?:\/\/|www\.)\S+/i', $content);
    if ($linkCount >= 2) {
        return 'شامل ' . $linkCount . ' لینک در یک نظر (احتمال تبلیغ اسپمی).';
    }

    if (preg_match('/(.)\1{7,}/u', $content)) {
        return 'تکرار غیرعادی یک کاراکتر در متن (احتمال اسپم).';
    }

    $letters = preg_replace('/[^\p{L}]/u', '', $content);
    $upper = preg_replace('/[^A-Z]/', '', $content);
    if (mb_strlen($letters) >= 20 && mb_strlen($upper) / max(1, mb_strlen($letters)) > 0.75) {
        return 'نوشتار عمدتاً با حروف بزرگ (الگوی رایج اسپم).';
    }

    return null;
}
