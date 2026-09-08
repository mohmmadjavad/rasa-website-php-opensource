<div class="editor-backdrop" id="articleEditorBackdrop">
  <div class="editor-panel">
    <header class="editor-header">
      <h2 id="editorTitle">وبلاگ جدید</h2>
      <div class="editor-header-actions">
        <span class="form-msg" id="articleFormMsg"></span>
        <button class="btn-ghost" type="button" id="editorCancelBtn">انصراف</button>
        <button class="btn-ghost" type="button" id="editorPreviewBtn">پیش‌نمایش</button>
        <button class="btn-secondary" type="button" id="editorSaveDraftBtn">ذخیره پیش‌نویس</button>
        <button class="btn-primary" type="button" id="editorPublishBtn">انتشار وبلاگ</button>
        <button class="modal-close-btn" id="editorCloseBtn" type="button" aria-label="بستن">&times;</button>
      </div>
    </header>

    <div class="editor-body">
      <div class="editor-main">
        <input type="text" id="fieldTitle" class="editor-title-input" placeholder="عنوان وبلاگ را اینجا بنویسید…" maxlength="220">

        <div class="slug-row">
          <span class="slug-label">اسلاگ:</span>
          <input type="text" id="fieldSlug" placeholder="به‌صورت خودکار از عنوان ساخته می‌شود">
        </div>

        <textarea id="fieldExcerpt" class="editor-excerpt" placeholder="خلاصه‌ای کوتاه و جذاب از وبلاگ (برای نمایش در لیست وبلاگ‌ها و نتایج گوگل)" maxlength="500" rows="2"></textarea>

        <div class="rte" id="rte">
          <?= resa_rte_toolbar_html('rte') ?>
          <div class="rte-editable" id="rteEditable" contenteditable="true" dir="rtl"></div>
          <textarea class="rte-html-view" id="rteHtmlView" spellcheck="false" placeholder="اینجا می‌توانید مستقیم کد HTML یا CSS بنویسید..."></textarea>
        </div>
      </div>

      <aside class="editor-sidebar">
        <div class="sidebar-card">
          <h4>تصویر شاخص</h4>
          <div class="cover-drop" id="coverDrop">
            <img id="coverPreview" alt="تصویر شاخص" style="display:none;">
            <span id="coverPlaceholder">برای انتخاب تصویر کلیک کنید<br><small>jpg, png, webp — حداکثر ۶ مگابایت</small></span>
          </div>
          <input type="file" id="coverFileInput" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;">
          <button type="button" class="table-del-btn" id="removeCoverBtn" style="display:none; margin-top:.6rem;">حذف تصویر</button>
        </div>

        <div class="sidebar-card">
          <div class="sidebar-card-head">
            <h4>دسته‌بندی</h4>
            <?php if ($canManageCategories): ?>
            <button type="button" class="btn-ghost--sm" id="editorManageCategoriesBtn">ویرایش و افزودن</button>
            <?php endif; ?>
          </div>
          <label class="field-label">دسته‌بندی اصلی</label>
          <select id="fieldCategory">
            <option value="">— انتخاب کنید —</option>
          </select>
          <label class="field-label">زیردسته‌ها</label>
          <div class="subcats-list" id="subcatsList">
            <span class="empty-hint">ابتدا دسته‌بندی اصلی را انتخاب کنید</span>
          </div>
        </div>

        <div class="sidebar-card">
          <h4>تگ‌ها</h4>
          <div class="tags-input" id="tagsInput">
            <input type="text" id="tagInputField" placeholder="تگ را بنویسید، Enter یا + بزنید">
          </div>
        </div>

        <div class="sidebar-card">
          <h4>اطلاعات وبلاگ</h4>
          <label class="field-label">نویسنده</label>
          <select id="fieldAuthor"></select>
          <label class="field-label">تاریخ و ساعت انتشار</label>
          <input type="text" id="fieldPublishedAt" class="jalali-datetime-input" placeholder="انتخاب نشده">
          <label class="field-label">زمان مطالعه (دقیقه)</label>
          <input type="number" id="fieldReadingTime" min="1" placeholder="محاسبه خودکار">
        </div>

        <div class="sidebar-card">
          <h4>سئو (SEO)</h4>
          <label class="field-label">عنوان سئو <span class="char-count" id="metaTitleCount">۰/۶۰</span></label>
          <input type="text" id="fieldMetaTitle" maxlength="180" placeholder="در صورت خالی بودن، از عنوان وبلاگ استفاده می‌شود">
          <label class="field-label">توضیحات متا <span class="char-count" id="metaDescCount">۰/۱۶۰</span></label>
          <textarea id="fieldMetaDescription" maxlength="320" rows="3" placeholder="در صورت خالی بودن، از خلاصه استفاده می‌شود"></textarea>
          <label class="field-label">کلمه کلیدی فوکوس</label>
          <input type="text" id="fieldFocusKeyword" placeholder="مثلاً: پنجره دوجداره">
          <div class="seo-preview">
            <div class="seo-preview-title" id="seoPreviewTitle">عنوان وبلاگ در نتایج گوگل</div>
            <div class="seo-preview-url"><span id="seoPreviewDomain">example.com</span>/article.html?slug=<span id="seoPreviewSlug">slug</span></div>
            <div class="seo-preview-desc" id="seoPreviewDesc">توضیحات متا اینجا نمایش داده می‌شود...</div>
          </div>
        </div>
      </aside>
    </div>
  </div>
</div>

<!-- مودال مدیریت دسته‌بندی‌های پروژه -->
<div class="modal-backdrop" id="projectCategoryModal">
  <div class="modal-box modal-box--wide">
    <button class="modal-close-btn" id="projectCategoryModalCloseBtn" type="button" aria-label="بستن">&times;</button>
    <h3>مدیریت دسته‌بندی‌های پروژه</h3>
    <p class="settings-desc">دسته‌بندی‌هایی که در بخش پروژه‌ها قابل انتخاب هستند (جدا از دسته‌بندی‌های وبلاگ‌ها).</p>

    <form class="inline-form" id="addProjectCategoryForm">
      <input type="hidden" name="editing_id" id="pCatEditingId" value="">
      <input type="text" name="name" id="pCatNameInput" placeholder="نام دسته‌بندی پروژه" required minlength="2" maxlength="100">
      <button type="submit" class="btn-primary" id="pCatSubmitBtn">افزودن</button>
      <button type="button" class="btn-ghost" id="pCatCancelEditBtn" style="display:none;">لغو ویرایش</button>
    </form>
    <div class="form-msg" id="addProjectCategoryMsg"></div>

    <div class="cat-tree" id="projectCategoryTree">
      <div class="empty-state">در حال بارگذاری...</div>
    </div>
  </div>
</div>

