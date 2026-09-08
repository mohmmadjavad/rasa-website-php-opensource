<?php
/**
 * install/install.php
 * صفحه‌ی نصب یک‌باره برای ساخت اولین حساب ادمین.
 * بعد از استفاده، این پوشه را کامل از روی هاست حذف کن یا با .htaccess ببند.
 */

define('RESA_APP', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

resa_start_session();
$pdo = resa_db();

$stmt = $pdo->query("SELECT COUNT(*) FROM admins");
$adminExists = (int)$stmt->fetchColumn() > 0;

$error = '';
$success = '';

if ($adminExists) {
    $error = 'نصب قبلاً انجام شده است. یک ادمین در سیستم وجود دارد. برای امنیت، این پوشه (install) را از روی هاست حذف کنید.';
    if (!is_file(__DIR__ . '/.htaccess')) {
        @file_put_contents(__DIR__ . '/.htaccess', "Require all denied\n");
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!resa_csrf_check($_POST['csrf'] ?? '')) {
        $error = 'نشست شما منقضی شده، صفحه را رفرش کنید.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $password2 = (string)($_POST['password2'] ?? '');

        if (mb_strlen($username) < 3) {
            $error = 'نام کاربری باید حداقل ۳ کاراکتر باشد.';
        } elseif (mb_strlen($password) < 8) {
            $error = 'رمز عبور باید حداقل ۸ کاراکتر باشد.';
        } elseif ($password !== $password2) {
            $error = 'تکرار رمز عبور مطابقت ندارد.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$username, $hash]);
            $success = 'حساب ادمین با موفقیت ساخته شد. اکنون می‌توانید وارد پنل ادمین شوید. برای امنیت، پوشه install را حذف کنید.';
            $adminExists = true;

            // قفل خودکار: بلافاصله بعد از ساخت اولین ادمین، این پوشه با .htaccess
            // بسته می‌شود تا حتی اگر یادتان برود آن را حذف کنید، دیگر در دسترس نباشد.
            @file_put_contents(__DIR__ . '/.htaccess', "Require all denied\n");
        }
    }
}

$csrf = resa_csrf_token();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>نصب اولیه | رسا تیم</title>
<link rel="icon" href="../assets/logo/favicon.svg" type="image/svg+xml">
<link rel="icon" href="../assets/logo/favicon-96x96.png" type="image/png" sizes="96x96">
<link rel="shortcut icon" href="../assets/logo/favicon.ico">
<link rel="apple-touch-icon" sizes="180x180" href="../assets/logo/apple-touch-icon.png">
<link rel="manifest" href="../assets/logo/site.webmanifest">
<meta name="theme-color" content="#171a1a">
<link href="../assets/vendor/vazirmatn/Vazirmatn-font-face.css" rel="stylesheet">
<style>
  :root{
    --c-ink:#171a1a; --c-deep:#125454; --c-teal:#0c8d8d;
    --c-cyan:#00ffff; --c-cyan-2:#7bfeff; --c-cyan-3:#b9feff; --c-mist:#f6fdff;
  }
  *{box-sizing:border-box;}
  body{
    margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
    background:radial-gradient(1200px 600px at 50% -10%, var(--c-deep), var(--c-ink));
    font-family:"Vazirmatn","Segoe UI",sans-serif; direction:rtl; padding:24px;
  }
  .card{
    background:var(--c-mist); color:var(--c-ink); width:100%; max-width:420px;
    border-radius:22px; padding:2.2rem; box-shadow:0 25px 60px rgba(0,0,0,.45);
    border:1px solid rgba(0,255,255,.25);
  }
  h1{ font-size:1.3rem; margin:0 0 .3rem; color:var(--c-deep); }
  p.sub{ margin:0 0 1.6rem; color:var(--c-teal); font-size:.88rem; }
  label{ display:block; font-size:.85rem; margin:1rem 0 .4rem; color:var(--c-deep); font-weight:600; }
  input{
    width:100%; padding:.7rem .9rem; border-radius:12px; border:1.5px solid #d8f3f3;
    font-family:inherit; font-size:.95rem; background:#fff; outline:none; transition:border-color .2s;
  }
  input:focus{ border-color:var(--c-teal); }
  button{
    margin-top:1.6rem; width:100%; padding:.85rem; border:0; border-radius:12px;
    background:linear-gradient(135deg, var(--c-teal), var(--c-deep)); color:var(--c-mist);
    font-family:inherit; font-size:1rem; font-weight:700; cursor:pointer; transition:filter .2s;
  }
  button:hover{ filter:brightness(1.1); }
  .msg{ padding:.8rem 1rem; border-radius:12px; font-size:.85rem; margin-bottom:1rem; }
  .msg.error{ background:#ffe9e9; color:#b12525; }
  .msg.success{ background:#e3fff5; color:#0c7a4f; }
  a.link{ color:var(--c-teal); font-weight:700; }
</style>
</head>
<body>
  <div class="card">
    <h1>نصب اولیه پنل ادمین</h1>
    <p class="sub">رسا تیم — ساخت اولین حساب مدیر سایت</p>

    <?php if ($error): ?><div class="msg error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($success): ?><div class="msg success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <?php if (!$adminExists): ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <label>نام کاربری ادمین</label>
      <input type="text" name="username" required minlength="3" maxlength="60">
      <label>رمز عبور</label>
      <input type="password" name="password" required minlength="8">
      <label>تکرار رمز عبور</label>
      <input type="password" name="password2" required minlength="8">
      <button type="submit">ساخت حساب ادمین</button>
    </form>
    <?php else: ?>
      <a class="link" href="../rasa-manager/login.php">رفتن به صفحه ورود پنل ادمین ←</a>
    <?php endif; ?>
  </div>
</body>
</html>
