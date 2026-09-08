<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();

if (!isset($_FILES['image'])) {
    api_respond(false, null, 'فایلی ارسال نشده است.');
}

$result = resa_upload_image($_FILES['image'], 'articles/content');
if (!$result['ok']) {
    api_respond(false, null, $result['message']);
}

api_respond(true, ['url' => $result['path']], 'تصویر آپلود شد.');
