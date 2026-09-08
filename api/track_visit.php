<?php
/**
 * api/track_visit.php
 * ثبت سبک بازدید صفحات سایت — برای آمار «بازدید سایت» در پنل ادمین.
 * این اندپوینت عمومی است (بدون نیاز به ورود) و توسط js/main.js در هر
 * بارگذاری صفحه به‌صورت یک درخواست سبک (beacon) صدا زده می‌شود.
 */

define('RESA_APP', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode((string)$raw, true);
$path = is_array($data) ? (string)($data['path'] ?? '') : '';
$path = trim($path);

if ($path === '') {
    $path = '/';
}
// فقط کاراکترهای مجاز در مسیر را نگه می‌داریم (بدون دامنه یا اسکریپت)
$path = preg_replace('/[^\p{L}\p{N}\/_\-\.\?=&]/u', '', $path);
if ($path === null || $path === '') {
    $path = '/';
}
if (mb_strlen($path) > 255) {
    $path = mb_substr($path, 0, 255);
}

try {
    $pdo = resa_db();
    resa_track_visit($pdo, $path);
} catch (Throwable $e) {
    // خطای احتمالی دیتابیس نباید در پاسخ به کاربر سایت منعکس شود
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
