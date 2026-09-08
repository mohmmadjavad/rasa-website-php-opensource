<div class="modal-backdrop" id="editAdminModal">
  <div class="modal-box modal-box--wide">
    <button class="modal-close-btn" id="editAdminCloseBtn" type="button" aria-label="بستن">&times;</button>
    <h3>ویرایش ادمین</h3>
    <p class="settings-desc" id="editAdminTarget">هر کدام از فیلدهای زیر که خالی بماند، تغییری نمی‌کند.</p>

    <form class="inline-form admin-form-grid" id="editAdminForm">
      <div class="admin-form-basic-row">
        <input type="hidden" name="id" id="editAdminId">
        <input type="text" name="username" id="editAdminUsername" placeholder="نام کاربری جدید" minlength="3" maxlength="60">
        <input type="password" name="password" id="editAdminPassword" placeholder="رمز عبور جدید (حداقل ۸ کاراکتر)" minlength="8">
      </div>

      <div id="editAdminPermissionsWrap">
        <?= resa_permission_fields_inline('edit_blog_scope') ?>
      </div>

      <button type="submit" class="btn-primary">ذخیره تغییرات</button>
    </form>
    <div class="form-msg" id="editAdminMsg"></div>
  </div>
</div>

<!-- مودال مدیریت دسته‌بندی‌ها -->
