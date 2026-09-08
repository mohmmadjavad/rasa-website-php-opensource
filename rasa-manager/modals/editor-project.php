<div class="editor-backdrop" id="projectEditorBackdrop">
  <div class="editor-panel">
    <header class="editor-header">
      <h2 id="pEditorTitle">پروژه جدید</h2>
      <div class="editor-header-actions">
        <span class="form-msg" id="projectFormMsg"></span>
        <button class="btn-ghost" type="button" id="pEditorPreviewBtn">پیش‌نمایش</button>
        <button class="btn-secondary" type="button" id="pEditorSaveDraftBtn">ذخیره پیش‌نویس</button>
        <button class="btn-primary" type="button" id="pEditorPublishBtn">انتشار پروژه</button>
        <button class="modal-close-btn" id="pEditorCloseBtn" type="button" aria-label="بستن">&times;</button>
      </div>
    </header>

    <div class="editor-body">
      <div class="editor-main">
        <input type="text" id="pFieldTitle" class="editor-title-input" placeholder="عنوان پروژه را اینجا بنویسید…" maxlength="200">

        <div class="slug-row">
          <span class="slug-label">اسلاگ:</span>
          <input type="text" id="pFieldSlug" placeholder="به‌صورت خودکار از عنوان ساخته می‌شود">
        </div>

        <textarea id="pFieldExcerpt" class="editor-excerpt" placeholder="خلاصه‌ای کوتاه و جذاب از پروژه (برای نمایش در لیست پروژه‌ها)" maxlength="500" rows="2"></textarea>

        <div class="rte" id="pRte">
          <?= resa_rte_toolbar_html('pRte') ?>
          <div class="rte-editable" id="pRteEditable" contenteditable="true" dir="rtl"></div>
          <textarea class="rte-html-view" id="pRteHtmlView" spellcheck="false" placeholder="اینجا می‌توانید مستقیم کد HTML بنویسید..."></textarea>
        </div>
      </div>

      <aside class="editor-sidebar">
        <div class="sidebar-card">
          <h4>رسانه شاخص</h4>
          <div class="cover-type-toggle" id="coverTypeToggle">
            <button type="button" class="cover-type-btn is-active" data-type="image">عکس</button>
            <button type="button" class="cover-type-btn" data-type="video">ویدیو</button>
          </div>

          <div id="pCoverImageWrap">
            <div class="cover-drop" id="pCoverDrop">
              <img id="pCoverPreview" alt="تصویر شاخص" style="display:none;">
              <span id="pCoverPlaceholder">برای انتخاب تصویر کلیک کنید<br><small>jpg, png, webp — حداکثر ۶ مگابایت</small></span>
            </div>
            <input type="file" id="pCoverFileInput" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;">
            <button type="button" class="table-del-btn" id="pRemoveCoverBtn" style="display:none; margin-top:.6rem;">حذف تصویر</button>
          </div>

          <div id="pCoverVideoWrap" style="display:none;">
            <div class="cover-drop cover-drop--video" id="pCoverVideoDrop">
              <video id="pCoverVideoPreview" style="display:none;" controls muted></video>
              <span id="pCoverVideoPlaceholder">برای انتخاب فایل ویدیو کلیک کنید<br><small>mp4, webm — حداکثر ۶۰ مگابایت</small></span>
            </div>
            <input type="file" id="pCoverVideoFileInput" accept="video/mp4,video/webm,video/ogg,video/quicktime" style="display:none;">
            <label class="field-label">یا آدرس ویدیو (مثلاً لینک آپارات/یوتیوب/فایل مستقیم)</label>
            <input type="text" id="pCoverVideoUrlInput" placeholder="https://...">
            <button type="button" class="table-del-btn" id="pRemoveCoverVideoBtn" style="display:none; margin-top:.6rem;">حذف ویدیو</button>
          </div>
        </div>

        <div class="sidebar-card">
          <div class="sidebar-card-head">
            <h4>دسته‌بندی‌ها</h4>
            <?php if ($canManageProjectCategories): ?>
            <button type="button" class="btn-ghost--sm" id="pEditorManageCategoriesBtn">ویرایش و افزودن</button>
            <?php endif; ?>
          </div>
          <label class="field-label">دسته‌بندی‌های پروژه <span class="char-count">چند انتخابی</span></label>
          <div class="subcats-list" id="pCategoriesList">
            <span class="empty-hint">در حال بارگذاری دسته‌بندی‌ها...</span>
          </div>
        </div>

        <div class="sidebar-card" id="pBrandsCard" style="display:none;">
          <div class="sidebar-card-head">
            <h4>برندها</h4>
            <?php if ($canManageProjectCategories): ?>
            <button type="button" class="btn-ghost--sm" id="pEditorManageBrandsBtn">ویرایش و افزودن</button>
            <?php endif; ?>
          </div>
          <label class="field-label">برندهایی که با این پروژه کار کرده‌ایم <span class="char-count">چند انتخابی</span></label>
          <div class="subcats-list" id="pBrandsList">
            <span class="empty-hint">در حال بارگذاری برندها...</span>
          </div>
        </div>

        <div class="sidebar-card">
          <h4>تاریخ انتشار</h4>
          <input type="text" id="pFieldPublishedAt" class="jalali-datetime-input" placeholder="انتخاب نشده">
        </div>

        <div class="sidebar-card">
          <h4>محل نمایش پروژه</h4>
          <p class="settings-desc">این پروژه کجا منتشر شود؟</p>
          <select id="pFieldDisplayScope" class="field-control">
            <option value="both">صفحه پروژه‌های سایت + صفحه پروفایل اعضا</option>
            <option value="projects_page">فقط صفحه پروژه‌های سایت</option>
            <option value="profile_only">فقط صفحه پروفایل اعضا</option>
          </select>
        </div>

        <div class="sidebar-card">
          <h4>تیم پروژه — ادمین‌ها</h4>
          <p class="settings-desc">از بین ادمین‌های سایت انتخاب کنید. ادمینی که پروفایلش (نام نمایشی) را تکمیل نکرده باشد، قابل انتخاب نیست.</p>
          <div class="admin-picker-list" id="adminPickerList">
            <div class="empty-state">در حال بارگذاری ادمین‌ها...</div>
          </div>
        </div>

        <div class="sidebar-card">
          <h4>تیم پروژه — افراد بدون حساب ادمین</h4>
          <p class="settings-desc">افرادی که حساب ادمین ندارند را می‌توانید دستی با نام، سمت و عکس اضافه کنید.</p>
          <div class="manual-members-list" id="manualMembersList"></div>
          <button type="button" class="btn-ghost" id="addManualMemberBtn" style="margin-top:.6rem; width:100%;">+ افزودن عضو</button>
        </div>

        <div class="sidebar-card">
          <h4>سئو (SEO)</h4>
          <label class="field-label">عنوان سئو</label>
          <input type="text" id="pFieldMetaTitle" maxlength="180" placeholder="در صورت خالی بودن، از عنوان پروژه استفاده می‌شود">
          <label class="field-label">توضیحات متا</label>
          <textarea id="pFieldMetaDescription" maxlength="320" rows="3" placeholder="در صورت خالی بودن، از خلاصه استفاده می‌شود"></textarea>
        </div>
      </aside>
    </div>
  </div>
</div>

