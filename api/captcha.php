<?php
/**
 * api/captcha.php
 * تولید کپچای تصویری با SVG خالص — بدون نیاز به افزونه GD،
 * تا روی هر هاست اشتراکی بدون تنظیم اضافه کار کند.
 */

define('RESA_APP', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

resa_start_session();

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$width  = 150;
$height = 50;

$purpose = ($_GET['for'] ?? '') === 'comment' ? 'comment' : 'contact';
$sessionKey     = $purpose . '_captcha';
$sessionTimeKey = $purpose . '_captcha_time';

$code = '';
for ($i = 0; $i < 5; $i++) {
    $code .= random_int(0, 9);
}
$_SESSION[$sessionKey]     = $code;
$_SESSION[$sessionTimeKey] = time();

$bg   = '#f6fdff';
$ink  = '#125454';
$teal = '#0c8d8d';
$noise = '#b9feff';

$svg  = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">';
$svg .= '<rect width="100%" height="100%" fill="' . $bg . '" rx="10"/>';

// خطوط نویز پس‌زمینه
for ($i = 0; $i < 6; $i++) {
    $x1 = random_int(0, $width);
    $y1 = random_int(0, $height);
    $x2 = random_int(0, $width);
    $y2 = random_int(0, $height);
    $svg .= '<line x1="' . $x1 . '" y1="' . $y1 . '" x2="' . $x2 . '" y2="' . $y2 . '" stroke="' . $noise . '" stroke-width="1.5" opacity="0.8"/>';
}

// نقاط نویز
for ($i = 0; $i < 40; $i++) {
    $cx = random_int(0, $width);
    $cy = random_int(0, $height);
    $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="1" fill="' . $teal . '" opacity="0.5"/>';
}

// رقم‌ها با چرخش و رنگ تصادفی برای دشوارتر کردن خواندن ماشینی
$colors = [$ink, $teal];
$spacing = intdiv($width - 20, strlen($code));
for ($i = 0; $i < strlen($code); $i++) {
    $digit = $code[$i];
    $x = 22 + $i * $spacing;
    $y = random_int(30, 36);
    $rotate = random_int(-18, 18);
    $color = $colors[array_rand($colors)];
    $fontSize = random_int(22, 27);
    $svg .= '<text x="' . $x . '" y="' . $y . '" font-size="' . $fontSize . '" font-family="Arial, sans-serif" font-weight="700" fill="' . $color . '" transform="rotate(' . $rotate . ' ' . $x . ' ' . $y . ')" text-anchor="middle">' . $digit . '</text>';
}

$svg .= '</svg>';

echo $svg;
