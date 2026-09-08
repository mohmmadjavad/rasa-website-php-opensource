<!-- مودال مدیریت برندهایی که با آن‌ها کار کرده‌ایم -->
<div class="modal-backdrop" id="brandModal">
  <div class="modal-box modal-box--wide">
    <button class="modal-close-btn" id="brandModalCloseBtn" type="button" aria-label="بستن">&times;</button>
    <h3>مدیریت برندها</h3>
    <p class="settings-desc">برندهایی که با رسا کار کرده‌اند؛ در پروژه‌ها قابل انتخاب هستند و در صفحه‌ی «درباره ما» نمایش داده می‌شوند.</p>

    <form class="inline-form" id="addBrandForm" enctype="multipart/form-data">
      <input type="hidden" name="editing_id" id="brandEditingId" value="">
      <input type="hidden" name="remove_logo" id="brandRemoveLogoFlag" value="">

      <label class="cover-drop cover-drop--small" id="brandLogoDrop" style="cursor:pointer;">
        <input type="file" id="brandLogoInput" name="logo_image" accept="image/*" hidden>
        <span id="brandLogoPlaceholder">+ لوگو</span>
        <img id="brandLogoPreview" style="display:none;" alt="لوگوی برند">
      </label>
      <button type="button" class="cat-row-del" id="brandLogoRemoveBtn" title="حذف لوگو" style="display:none;">&times;</button>

      <input type="text" name="name" id="brandNameInput" placeholder="نام برند" required minlength="2" maxlength="100">
      <button type="submit" class="btn-primary" id="brandSubmitBtn">افزودن</button>
      <button type="button" class="btn-ghost" id="brandCancelEditBtn" style="display:none;">لغو ویرایش</button>
    </form>
    <div class="form-msg" id="addBrandMsg"></div>

    <div class="cat-tree" id="brandTree">
      <div class="empty-state">در حال بارگذاری...</div>
    </div>
  </div>
</div>
