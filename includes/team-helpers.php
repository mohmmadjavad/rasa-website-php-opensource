<?php
/**
 * includes/team-helpers.php
 * توابع مشترک «کارت تیم ما» و «صفحه پروفایل عمومی»: تعریف شبکه‌های اجتماعی پشتیبانی‌شده
 * و ساخت لینک قابل کلیک از مقدار خام ذخیره‌شده در دیتابیس (برای admins و team_extra_members).
 */

if (!defined('RESA_APP')) {
    http_response_code(403);
    exit('Access denied.');
}

/**
 * شبکه‌های اجتماعی پشتیبانی‌شده: کلید (بدون پیشوند social_) => برچسب فارسی نمایشی.
 * ترتیب همین آرایه، ترتیب نمایش آیکون‌ها در کارت تیم و صفحه پروفایل را هم مشخص می‌کند.
 */
function resa_social_platform_defs(): array
{
    return [
        'telegram'  => 'تلگرام',
        'whatsapp'  => 'واتساپ',
        'instagram' => 'اینستاگرام',
        'pinterest' => 'پینترست',
        'x'         => 'ایکس (X)',
        'linkedin'  => 'لینکدین',
        'github'    => 'گیت‌هاب',
        'youtube'   => 'یوتیوب',
        'website'   => 'وبسایت',
        'email'     => 'ایمیل',
        'phone'     => 'تماس',
    ];
}

/**
 * ساخت لینک قابل کلیک از مقدار خامی که ادمین وارد کرده (یوزرنیم، شماره، یا لینک کامل).
 */
function resa_social_build_link(string $key, string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $bare = ltrim($value, '@');
    switch ($key) {
        case 'telegram':
            return 'https://t.me/' . $bare;
        case 'whatsapp':
            $digits = preg_replace('/[^0-9]/', '', $value);
            return $digits !== '' ? 'https://wa.me/' . $digits : '';
        case 'instagram':
            return 'https://instagram.com/' . $bare;
        case 'pinterest':
            return 'https://pinterest.com/' . $bare;
        case 'x':
            return 'https://x.com/' . $bare;
        case 'github':
            return 'https://github.com/' . $bare;
        case 'linkedin':
        case 'youtube':
        case 'website':
            return preg_match('#^https?://#i', $value) ? $value : 'https://' . $value;
        case 'email':
            return 'mailto:' . $value;
        case 'phone':
            return 'tel:' . preg_replace('/\s+/', '', $value);
        default:
            return $value;
    }
}

/**
 * از یک ردیف دیتابیس (admins یا team_extra_members) که ستون‌های social_* دارد،
 * فهرستی از شبکه‌های پرشده برمی‌گرداند: [['key'=>..,'label'=>..,'value'=>..,'url'=>..], ...]
 */
function resa_social_list_from_row(array $row): array
{
    $out = [];
    foreach (resa_social_platform_defs() as $key => $label) {
        $val = trim((string)($row['social_' . $key] ?? ''));
        if ($val === '') {
            continue;
        }
        $out[] = [
            'key'   => $key,
            'label' => $label,
            'value' => $val,
            'url'   => resa_social_build_link($key, $val),
        ];
    }

    // شبکه اجتماعی دلخواه: خود کاربر هم اسم شبکه و هم لینک کامل آن را وارد می‌کند
    $customLabel = trim((string)($row['social_custom_label'] ?? ''));
    $customUrl = trim((string)($row['social_custom_url'] ?? ''));
    if ($customLabel !== '' && $customUrl !== '') {
        $out[] = [
            'key'   => 'custom',
            'label' => $customLabel,
            'value' => $customUrl,
            'url'   => preg_match('#^https?://#i', $customUrl) ? $customUrl : 'https://' . $customUrl,
        ];
    }

    return $out;
}
