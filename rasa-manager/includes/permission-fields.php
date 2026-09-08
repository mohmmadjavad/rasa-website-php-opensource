<?php
/**
 * توابع تولید HTML چک‌باکس‌های دسترسی — در فرم «افزودن ادمین» و مودال «ویرایش ادمین» استفاده می‌شوند.
 */

function resa_permission_fields_html(string $radioName): string
{
    ob_start();
    ?>
    <div class="perm-panel">
      <div class="perm-group perm-group--dashboard">
        <div class="perm-group-head">
          <span class="perm-group-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="3.5" width="7.5" height="7.5" rx="1.4"/><rect x="13" y="3.5" width="7.5" height="5" rx="1.4"/><rect x="13" y="10.5" width="7.5" height="10" rx="1.4"/><rect x="3.5" y="13" width="7.5" height="7.5" rx="1.4"/></svg></span>
          <h5>داشبورد</h5>
        </div>
        <label class="perm-check"><span class="perm-check-text">دسترسی به تب داشبورد</span><input type="checkbox" data-perm="dashboard.access"></label>
      </div>
      <div class="perm-group perm-group--messages">
        <div class="perm-group-head">
          <span class="perm-group-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
          <h5>پیام‌ها</h5>
        </div>
        <label class="perm-check"><span class="perm-check-text">مشاهده و خواندن پیام‌ها</span><input type="checkbox" data-perm="messages.view"></label>
        <label class="perm-check"><span class="perm-check-text">حذف پیام‌ها</span><input type="checkbox" data-perm="messages.delete"></label>
      </div>
      <div class="perm-group perm-group--projects">
        <div class="perm-group-head">
          <span class="perm-group-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg></span>
          <h5>پروژه‌ها</h5>
        </div>
        <label class="perm-check"><span class="perm-check-text">دسترسی به تب پروژه‌ها<small>خاموش = تب اصلاً نمایش داده نمی‌شود</small></span><input type="checkbox" data-perm="projects.access"></label>
        <div class="perm-radio-row">
          <label class="perm-check perm-check--pill"><input type="radio" name="<?= htmlspecialchars($radioName, ENT_QUOTES, 'UTF-8') ?>_projects" value="own" data-perm="projects.scope"><span>فقط پروژه‌های تیم خودش</span></label>
          <label class="perm-check perm-check--pill"><input type="radio" name="<?= htmlspecialchars($radioName, ENT_QUOTES, 'UTF-8') ?>_projects" value="all" data-perm="projects.scope"><span>دسترسی کامل به همه</span></label>
        </div>
        <label class="perm-check"><span class="perm-check-text">ساخت دسته‌بندی پروژه</span><input type="checkbox" data-perm="projects.manage_categories"></label>
      </div>
      <div class="perm-group perm-group--blog">
        <div class="perm-group-head">
          <span class="perm-group-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></span>
          <h5>وبلاگ</h5>
        </div>
        <label class="perm-check"><span class="perm-check-text">دسترسی به بخش وبلاگ‌ها</span><input type="checkbox" data-perm="blog.access"></label>
        <div class="perm-radio-row">
          <label class="perm-check perm-check--pill"><input type="radio" name="<?= htmlspecialchars($radioName, ENT_QUOTES, 'UTF-8') ?>" value="own" data-perm="blog.scope"><span>فقط وبلاگ‌ها خودش</span></label>
          <label class="perm-check perm-check--pill"><input type="radio" name="<?= htmlspecialchars($radioName, ENT_QUOTES, 'UTF-8') ?>" value="all" data-perm="blog.scope"><span>دسترسی کامل به همه</span></label>
        </div>
        <label class="perm-check"><span class="perm-check-text">ساخت دسته‌بندی اصلی و فرعی</span><input type="checkbox" data-perm="blog.manage_categories"></label>
      </div>
      <div class="perm-group perm-group--comments">
        <div class="perm-group-head">
          <span class="perm-group-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8z"/></svg></span>
          <h5>نظرات کاربران</h5>
        </div>
        <label class="perm-check"><span class="perm-check-text">دسترسی به تب نظرات کاربران<small>خاموش = تب اصلاً نمایش داده نمی‌شود</small></span><input type="checkbox" data-perm="comments.access"></label>
        <div class="perm-radio-row">
          <label class="perm-check perm-check--pill"><input type="radio" name="<?= htmlspecialchars($radioName, ENT_QUOTES, 'UTF-8') ?>_comments" value="own" data-perm="comments.scope"><span>فقط نظرات وبلاگ‌ها خودش</span></label>
          <label class="perm-check perm-check--pill"><input type="radio" name="<?= htmlspecialchars($radioName, ENT_QUOTES, 'UTF-8') ?>_comments" value="all" data-perm="comments.scope"><span>دسترسی کامل به همه</span></label>
        </div>
      </div>
      <div class="perm-group perm-group--settings">
        <div class="perm-group-head">
          <span class="perm-group-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
          <h5>تنظیمات</h5>
        </div>
        <label class="perm-check"><span class="perm-check-text">مدیریت ادمین‌ها<small>افزودن/ویرایش/حذف ادمین‌های عادی — به‌جز سوپر ادمین</small></span><input type="checkbox" data-perm="settings.manage_admins"></label>
        <label class="perm-check"><span class="perm-check-text">تنظیمات سایت<small>تایید خودکار نظرات، حالت تعمیرات</small></span><input type="checkbox" data-perm="settings.manage_site"></label>
        <label class="perm-check"><span class="perm-check-text">آمار و گزارش<small>آمار بازدید سایت و گزارش فعالیت‌ها</small></span><input type="checkbox" data-perm="settings.view_stats"></label>
        <label class="perm-check"><span class="perm-check-text">پشتیبان‌گیری<small>ساخت، دانلود و بازیابی فایل پشتیبان</small></span><input type="checkbox" data-perm="settings.manage_backup"></label>
        <label class="perm-check"><span class="perm-check-text">سلامت سیستم<small>وضعیت دیتابیس، فضای سرور، آخرین پشتیبان‌ها</small></span><input type="checkbox" data-perm="settings.view_health"></label>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * پنل دسترسی‌ها به‌صورت لیستی (برای فرم افزودن ادمین)
 */
function resa_permission_fields_inline(string $radioName): string
{
    ob_start();
    ?>
    <div class="perm-panel-inline">
      <!-- داشبورد -->
      <div class="perm-group-inline">
        <div class="perm-group-head-inline">
          <span class="perm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="3.5" width="7.5" height="7.5" rx="1.4"/><rect x="13" y="3.5" width="7.5" height="5" rx="1.4"/><rect x="13" y="10.5" width="7.5" height="10" rx="1.4"/><rect x="3.5" y="13" width="7.5" height="7.5" rx="1.4"/></svg></span>
          <h5>داشبورد</h5>
        </div>
        <label class="perm-check-inline">
          <span class="perm-label">دسترسی به تب داشبورد <small>خاموش = تب نمایش داده نمی‌شود</small></span>
          <input type="checkbox" data-perm="dashboard.access">
        </label>
      </div>

      <!-- پیام‌ها -->
      <div class="perm-group-inline">
        <div class="perm-group-head-inline">
          <span class="perm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
          <h5>پیام‌ها</h5>
        </div>
        <label class="perm-check-inline">
          <span class="perm-label">مشاهده و خواندن پیام‌ها</span>
          <input type="checkbox" data-perm="messages.view">
        </label>
        <label class="perm-check-inline">
          <span class="perm-label">حذف پیام‌ها</span>
          <input type="checkbox" data-perm="messages.delete">
        </label>
      </div>

      <!-- پروژه‌ها -->
      <div class="perm-group-inline">
        <div class="perm-group-head-inline">
          <span class="perm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg></span>
          <h5>پروژه‌ها</h5>
        </div>
        <label class="perm-check-inline">
          <span class="perm-label">دسترسی به تب پروژه‌ها <small>خاموش = تب نمایش داده نمی‌شود</small></span>
          <input type="checkbox" data-perm="projects.access">
        </label>
        <div class="perm-radio-inline">
          <label class="perm-radio-option">
            <input type="radio" name="<?= htmlspecialchars($radioName, ENT_QUOTES, 'UTF-8') ?>_projects" value="own" data-perm="projects.scope">
            فقط پروژه‌های خودش
          </label>
          <label class="perm-radio-option">
            <input type="radio" name="<?= htmlspecialchars($radioName, ENT_QUOTES, 'UTF-8') ?>_projects" value="all" data-perm="projects.scope">
            همه پروژه‌ها
          </label>
        </div>
        <label class="perm-check-inline">
          <span class="perm-label">ساخت دسته‌بندی پروژه</span>
          <input type="checkbox" data-perm="projects.manage_categories">
        </label>
      </div>

      <!-- وبلاگ -->
      <div class="perm-group-inline">
        <div class="perm-group-head-inline">
          <span class="perm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></span>
          <h5>وبلاگ</h5>
        </div>
        <label class="perm-check-inline">
          <span class="perm-label">دسترسی به بخش وبلاگ‌ها</span>
          <input type="checkbox" data-perm="blog.access">
        </label>
        <div class="perm-radio-inline">
          <label class="perm-radio-option">
            <input type="radio" name="<?= htmlspecialchars($radioName, ENT_QUOTES, 'UTF-8') ?>" value="own" data-perm="blog.scope">
            فقط وبلاگ‌ها خودش
          </label>
          <label class="perm-radio-option">
            <input type="radio" name="<?= htmlspecialchars($radioName, ENT_QUOTES, 'UTF-8') ?>" value="all" data-perm="blog.scope">
            همه وبلاگ‌ها
          </label>
        </div>
        <label class="perm-check-inline">
          <span class="perm-label">ساخت دسته‌بندی اصلی و فرعی</span>
          <input type="checkbox" data-perm="blog.manage_categories">
        </label>
      </div>

      <!-- نظرات -->
      <div class="perm-group-inline">
        <div class="perm-group-head-inline">
          <span class="perm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8z"/></svg></span>
          <h5>نظرات کاربران</h5>
        </div>
        <label class="perm-check-inline">
          <span class="perm-label">دسترسی به تب نظرات کاربران <small>خاموش = تب نمایش داده نمی‌شود</small></span>
          <input type="checkbox" data-perm="comments.access">
        </label>
        <div class="perm-radio-inline">
          <label class="perm-radio-option">
            <input type="radio" name="<?= htmlspecialchars($radioName, ENT_QUOTES, 'UTF-8') ?>_comments" value="own" data-perm="comments.scope">
            فقط نظرات خودش
          </label>
          <label class="perm-radio-option">
            <input type="radio" name="<?= htmlspecialchars($radioName, ENT_QUOTES, 'UTF-8') ?>_comments" value="all" data-perm="comments.scope">
            همه نظرات
          </label>
        </div>
      </div>

      <!-- تنظیمات -->
      <div class="perm-group-inline">
        <div class="perm-group-head-inline">
          <span class="perm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
          <h5>تنظیمات</h5>
        </div>
        <label class="perm-check-inline">
          <span class="perm-label">مدیریت ادمین‌ها <small>افزودن/ویرایش/حذف ادمین‌های عادی</small></span>
          <input type="checkbox" data-perm="settings.manage_admins">
        </label>
        <label class="perm-check-inline">
          <span class="perm-label">تنظیمات سایت <small>تایید خودکار نظرات، حالت تعمیرات</small></span>
          <input type="checkbox" data-perm="settings.manage_site">
        </label>
        <label class="perm-check-inline">
          <span class="perm-label">آمار و گزارش <small>آمار بازدید سایت و گزارش فعالیت‌ها</small></span>
          <input type="checkbox" data-perm="settings.view_stats">
        </label>
        <label class="perm-check-inline">
          <span class="perm-label">پشتیبان‌گیری <small>ساخت، دانلود و بازیابی فایل پشتیبان</small></span>
          <input type="checkbox" data-perm="settings.manage_backup">
        </label>
        <label class="perm-check-inline">
          <span class="perm-label">سلامت سیستم <small>وضعیت دیتابیس، فضای سرور، آخرین پشتیبان‌ها</small></span>
          <input type="checkbox" data-perm="settings.view_health">
        </label>
      </div>
    </div>
    <?php
    return ob_get_clean();
}
?>
