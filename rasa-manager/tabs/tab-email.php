    <!-- ================= تب ایمیل ================= -->
    <section class="tab-panel <?= $defaultTab === 'email' ? 'is-active' : '' ?>" id="tab-email">
      <?php
      $myMailbox = resa_get_admin_mailbox($pdo, resa_current_admin_id());
      ?>

      <?php if (!$myMailbox): ?>
        <div class="empty-state email-empty-state">
          <p>هنوز صندوق ایمیلی برای حساب شما ساخته نشده.</p>
          <p class="email-empty-hint">
            اگر همین الان ادمین ساخته شده و این پیام را می‌بینید، از سوپر ادمین بخواهید تنظیمات اتصال به cPanel
            (بخش <code>CPANEL_*</code> در <code>config.php</code>) را بررسی کند و دوباره حسابتان را باز کند.
          </p>
        </div>
      <?php else: ?>
        <div class="email-toolbar">
          <div class="stat-chip">آدرس ایمیل شما: <b><?= htmlspecialchars($myMailbox['email_address'], ENT_QUOTES, 'UTF-8') ?></b></div>
          <button class="btn-ghost" id="reloadWebmailBtn" type="button">بارگذاری مجدد</button>
          <a class="btn-ghost" id="openWebmailNewTabBtn" href="#" target="_blank" rel="noopener">باز کردن در تب جدید</a>
        </div>
        <div class="email-frame-wrap">
          <iframe id="webmailFrame" class="email-frame" title="صندوق ایمیل" loading="lazy"></iframe>
        </div>
      <?php endif; ?>
    </section>
