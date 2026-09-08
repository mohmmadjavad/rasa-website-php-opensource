<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_csrf();

if (!isset($_FILES['media'])) {
    api_respond(false, null, 'فایلی ارسال نشده است.');
}

$kind = ($_POST['kind'] ?? '') === 'audio' ? 'audio' : 'video';
$section = ($_POST['section'] ?? '') === 'articles' ? 'articles' : 'projects';
$dir = $section . '/content/' . $kind;

$result = resa_upload_media($_FILES['media'], $dir, $kind);
if (!$result['ok']) {
    api_respond(false, null, $result['message']);
}

api_respond(true, ['url' => $result['path']], 'فایل آپلود شد.');
