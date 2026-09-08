<div class="modal-backdrop" id="categoryModal">
  <div class="modal-box modal-box--wide">
    <button class="modal-close-btn" id="categoryModalCloseBtn" type="button" aria-label="بستن">&times;</button>
    <h3>مدیریت دسته‌بندی‌ها</h3>
    <p class="settings-desc"><?= $isSuperAdmin ? 'یک دسته‌بندی اصلی بسازید، سپس زیردسته‌های آن را اضافه کنید.' : 'می‌توانید دسته‌بندی یا زیردسته جدید اضافه کنید.' ?></p>

    <form class="inline-form" id="addCategoryForm">
      <input type="hidden" name="editing_id" id="catEditingId" value="">
      <input type="text" name="name" id="catNameInput" placeholder="نام دسته‌بندی" required minlength="2" maxlength="100">
      <select name="parent_id" id="catParentSelect">
        <option value="">دسته‌بندی اصلی (سطح بالا)</option>
      </select>
      <button type="submit" class="btn-primary" id="catSubmitBtn">افزودن</button>
      <button type="button" class="btn-ghost" id="catCancelEditBtn" style="display:none;">لغو ویرایش</button>
    </form>

    <div class="cat-main-extra" id="catMainExtra">
      <p class="settings-desc">این فیلدها فقط برای دسته‌بندی‌های اصلی (سطح بالا) استفاده می‌شوند.</p>
      <div class="cat-main-extra-row">
        <div class="cat-icon-col">
          <label class="field-label">آیکون</label>
          <div class="cover-drop cover-drop--small" id="catIconDrop">
            <img id="catIconPreview" alt="آیکون" style="display:none;">
            <span id="catIconPlaceholder">آیکون</span>
          </div>
          <input type="file" id="catIconInput" accept="image/*" style="display:none;">
          <button type="button" class="table-del-btn" id="catRemoveIconBtn" style="display:none; margin-top:.5rem;">حذف آیکون</button>
        </div>
        <div class="cat-poster-col">
          <label class="field-label">عکس پوستر</label>
          <div class="cover-drop" id="catPosterDrop">
            <img id="catPosterPreview" alt="پوستر" style="display:none;">
            <span id="catPosterPlaceholder">برای انتخاب عکس پوستر کلیک کنید</span>
          </div>
          <input type="file" id="catPosterInput" accept="image/*" style="display:none;">
          <button type="button" class="table-del-btn" id="catRemovePosterBtn" style="display:none; margin-top:.5rem;">حذف پوستر</button>
        </div>
      </div>
      <label class="field-label">توضیح کوتاه دسته‌بندی</label>
      <textarea id="catDescriptionInput" rows="2" maxlength="500" placeholder="توضیحی کوتاه درباره این دسته‌بندی..."></textarea>
    </div>

    <div class="form-msg" id="addCategoryMsg"></div>

    <div class="cat-tree" id="categoryTree">
      <div class="empty-state">در حال بارگذاری...</div>
    </div>
  </div>
</div>

<!-- کشوی ویرایشگر وبلاگ -->
