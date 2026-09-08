<?php
require_once __DIR__ . '/_bootstrap.php';
resa_require_super_admin_api();

$defaults = [
    'about' => [
        'hero_eyebrow' => 'رسا تیم',
        'hero_title'   => 'داستانی که با یک اتاق کوچک شروع شد',
        'hero_sub'     => 'از یک هسته‌ی چندنفره‌ی دانشجویی تا تیمی که امروز فیلم می‌سازد، موسیقی ضبط می‌کند و هویت بصری طراحی می‌کند؛ این‌جا روایت کامل رسا را می‌خوانید.',
        'story_title'  => 'ما اینگونه متولد شدیم:',
        'story_body'   => "اوایل پاییز ۱۴۰۲ بود که چند دانشجوی دانشگاه صنعتی اراک، توی یک اتاق کوچک توی دانشکده جمع شدند.\n\nکم‌کم تعداد اعضا بیشتر شد و رسا به شکل امروزی درآمد.",
    ],
    'contact' => [
        'hero_title' => 'بیایید با هم گفت‌وگو کنیم',
        'hero_sub'   => 'هر سوال، پیشنهاد یا ایده‌ای داری، خوشحال می‌شویم بشنویم. تیم رسا در سریع‌ترین زمان ممکن پاسخگوی توست.',
        'address'    => 'اراک، خیابان شهید بهشتی\nدانشگاه صنعتی اراک',
        'phone'      => '+989000000000',
        'email'      => 'info@example.com',
        'hours_days' => 'شنبه تا چهارشنبه',
        'hours_time' => '۹ صبح تا ۱۷',
    ],
];

function resa_page_json(PDO $pdo, string $key, array $default): array
{
    $raw = resa_get_setting($pdo, $key, null);
    if (!$raw) return $default;
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) return $default;
    return array_merge($default, $decoded);
}

api_respond(true, [
    'about'   => resa_page_json($pdo, 'page_about', $defaults['about']),
    'contact' => resa_page_json($pdo, 'page_contact', $defaults['contact']),
]);
