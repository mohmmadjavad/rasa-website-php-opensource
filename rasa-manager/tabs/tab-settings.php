    <section class="tab-panel <?= $defaultTab === 'settings' ? 'is-active' : '' ?>" id="tab-settings">

      <div class="ios-settings" id="iosSettings">

        <div class="ios-settings-list" id="settingsList">
          <div class="ios-row-group" id="pwaInstallRowGroup">
            <button type="button" class="ios-row" id="installAdminAppRow">
              <span class="ios-row-icon ios-icon-teal">📲</span>
              <span class="ios-row-text">
                <span class="ios-row-title">نصب برنامه پنل ادمین</span>
                <span class="ios-row-sub" id="installAdminAppSub">برای دسترسی سریع‌تر، پنل ادمین را به‌صورت اپلیکیشن نصب کنید</span>
              </span>
              <span class="ios-row-chevron" id="installAdminAppChevron">‹</span>
            </button>
          </div>
          <div class="ios-row-group">
            <button type="button" class="ios-row" data-subtab="profile">
              <span class="ios-row-icon ios-icon-blue">👤</span>
              <span class="ios-row-text">
                <span class="ios-row-title">پروفایل و امنیت</span>
                <span class="ios-row-sub">اطلاعات شخصی، شبکه‌های اجتماعی و رمز عبور</span>
              </span>
              <span class="ios-row-chevron">‹</span>
            </button>
            <?php if ($canManageAdmins): ?>
            <button type="button" class="ios-row" data-subtab="admins">
              <span class="ios-row-icon ios-icon-purple">🛡️</span>
              <span class="ios-row-text">
                <span class="ios-row-title">ادمین‌ها</span>
                <span class="ios-row-sub">افزودن، ویرایش و مدیریت دسترسی ادمین‌ها</span>
              </span>
              <span class="ios-row-chevron">‹</span>
            </button>
            <?php endif; ?>
            <?php if ($isSuperAdmin): ?>
            <button type="button" class="ios-row" data-subtab="team-cards">
              <span class="ios-row-icon ios-icon-blue">🪪</span>
              <span class="ios-row-text">
                <span class="ios-row-title">کارت اعضای تیم</span>
                <span class="ios-row-sub">ویرایش، ترتیب و افزودن کارت‌های بخش «تیم ما»</span>
              </span>
              <span class="ios-row-chevron">‹</span>
            </button>
            <button type="button" class="ios-row" data-subtab="awards">
              <span class="ios-row-icon ios-icon-orange">🏆</span>
              <span class="ios-row-text">
                <span class="ios-row-title">دستاوردها و جوایز</span>
                <span class="ios-row-sub">افزودن، حذف و ترتیب تصاویر بخش «دستاوردی چشم‌نواز»</span>
              </span>
              <span class="ios-row-chevron">‹</span>
            </button>
            <button type="button" class="ios-row" data-subtab="dev-team">
              <span class="ios-row-icon ios-icon-purple">👨‍💻</span>
              <span class="ios-row-text">
                <span class="ios-row-title">افراد توسعه دهنده</span>
                <span class="ios-row-sub">انتخاب و ترتیب ادمین‌هایی که در صفحه تماس با ما نمایش داده می‌شوند</span>
              </span>
              <span class="ios-row-chevron">‹</span>
            </button>
            <?php endif; ?>
          </div>

          <?php if ($canManageSite || $canViewStats || $canManageBackup || $canViewHealth): ?>
          <div class="ios-row-group">
            <?php if ($canManageSite): ?>
            <button type="button" class="ios-row" data-subtab="site">
              <span class="ios-row-icon ios-icon-teal">⚙️</span>
              <span class="ios-row-text">
                <span class="ios-row-title">سایت</span>
                <span class="ios-row-sub">تایید نظرات و حالت تعمیرات</span>
              </span>
              <span class="ios-row-chevron">‹</span>
            </button>
            <?php endif; ?>
            <?php if ($canViewStats): ?>
            <button type="button" class="ios-row" data-subtab="stats">
              <span class="ios-row-icon ios-icon-orange">📊</span>
              <span class="ios-row-text">
                <span class="ios-row-title">آمار و گزارش</span>
                <span class="ios-row-sub">آمار بازدید سایت و گزارش فعالیت‌ها</span>
              </span>
              <span class="ios-row-chevron">‹</span>
            </button>
            <?php endif; ?>
            <?php if ($canManageBackup): ?>
            <button type="button" class="ios-row" data-subtab="backup">
              <span class="ios-row-icon ios-icon-gray">🗄️</span>
              <span class="ios-row-text">
                <span class="ios-row-title">پشتیبان‌گیری</span>
                <span class="ios-row-sub">تهیه و بازیابی نسخه پشتیبان کامل یا بخشی از دیتابیس و رسانه‌ها</span>
              </span>
              <span class="ios-row-chevron">‹</span>
            </button>
            <?php endif; ?>
            <?php if ($canViewHealth): ?>
            <button type="button" class="ios-row" data-subtab="health">
              <span class="ios-row-icon ios-icon-green">💚</span>
              <span class="ios-row-text">
                <span class="ios-row-title">سلامت سیستم</span>
                <span class="ios-row-sub">وضعیت دیتابیس، فضای سرور و آخرین پشتیبان‌ها</span>
              </span>
              <span class="ios-row-chevron">‹</span>
            </button>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>

        <div class="ios-settings-detail" id="settingsDetail">
          <div class="ios-detail-header">
            <button type="button" class="ios-back-btn" id="settingsBackBtn">بازگشت</button>
            <h3 class="ios-detail-title" id="settingsDetailTitle"></h3>
          </div>

          <div class="ios-detail-body">

      <div class="settings-subpanel is-active" data-subtab-panel="profile">
        <div class="settings-block">
          <h2>پروفایل من</h2>
          <p class="settings-desc">بیوگرافی خلاصه در کارت نویسنده زیر وبلاگ‌ها نمایش داده می‌شود؛ بیوگرافی مفصل در صفحه اختصاصی خودتان.</p>

          <div class="profile-form">
            <div class="profile-avatars-row" style="display:flex; flex-direction:column; gap:1.4rem;">
            <div class="profile-avatar-col">
              <div class="cover-drop cover-drop--round" id="profileAvatarDrop">
                <img id="profileAvatarPreview" alt="عکس پروفایل" style="display:none;">
                <span id="profileAvatarPlaceholder">آپلود عکس</span>
              </div>
              <input type="file" id="profileAvatarInput" accept="image/jpeg,image/png,image/webp" style="display:none;">
              <button type="button" class="table-del-btn" id="removeProfileAvatarBtn" style="margin-top:.6rem;">حذف عکس</button>
            </div>
            <div class="profile-avatar-col">
              <div class="cover-drop cover-drop--small" id="profileCardImageDrop">
                <img id="profileCardImagePreview" alt="عکس کارت تیم" style="display:none;">
                <span id="profileCardImagePlaceholder" style="font-size:.68rem;">عکس کارت تیم ما</span>
              </div>
              <input type="file" id="profileCardImageInput" accept="image/jpeg,image/png,image/webp" style="display:none;">
              <button type="button" class="table-del-btn" id="removeProfileCardImageBtn" style="margin-top:.6rem;">حذف عکس</button>
            </div>
            </div>
            <div class="profile-fields-col">
              <label class="field-label">نام نمایشی</label>
              <input type="text" id="profileDisplayName" placeholder="نام و نام خانوادگی" maxlength="100">
              <label class="field-label">عنوان شغلی</label>
              <input type="text" id="profileJobTitle" placeholder="مثلاً: توسعه‌دهنده فرانت‌اند" maxlength="160">
              <label class="field-label">بیوگرافی خلاصه</label>
              <textarea id="profileBioShort" rows="2" maxlength="240" placeholder="یک یا دو جمله کوتاه درباره خودتان... (زیر وبلاگ‌ها و در کارت تیم ما نمایش داده می‌شود)"></textarea>
              <label class="field-label">بیوگرافی مفصل</label>
              <textarea id="profileBioFull" rows="6" maxlength="4000" placeholder="معرفی کامل و مفصل خودتان... (در صفحه پروفایل عمومی نمایش داده می‌شود)"></textarea>

              <div class="profile-socials-grid">
                <div>
                  <label class="field-label">تلگرام</label>
                  <input type="text" id="profileTelegram" placeholder="@username">
                </div>
                <div>
                  <label class="field-label">واتساپ</label>
                  <input type="text" id="profileWhatsapp" placeholder="989xxxxxxxxx">
                </div>
                <div>
                  <label class="field-label">اینستاگرام</label>
                  <input type="text" id="profileInstagram" placeholder="@username">
                </div>
                <div>
                  <label class="field-label">گیت‌هاب</label>
                  <input type="text" id="profileGithub" placeholder="username">
                </div>
                <div>
                  <label class="field-label">ایمیل</label>
                  <input type="email" id="profileEmail" placeholder="name@example.com">
                </div>
                <div>
                  <label class="field-label">شماره تماس</label>
                  <input type="text" id="profilePhone" placeholder="09xxxxxxxxx">
                </div>
                <div>
                  <label class="field-label">لینکدین</label>
                  <input type="text" id="profileLinkedin" placeholder="لینک پروفایل">
                </div>
                <div>
                  <label class="field-label">ایکس (X)</label>
                  <input type="text" id="profileX" placeholder="@username">
                </div>
                <div>
                  <label class="field-label">یوتیوب</label>
                  <input type="text" id="profileYoutube" placeholder="لینک کانال">
                </div>
                <div>
                  <label class="field-label">وبسایت</label>
                  <input type="text" id="profileWebsite" placeholder="example.com">
                </div>
                <div>
                  <label class="field-label">پینترست</label>
                  <input type="text" id="profilePinterest" placeholder="username">
                </div>
              </div>

              <label class="field-label" style="margin-top:.9rem;">شبکه اجتماعی دلخواه</label>
              <p class="settings-desc" style="margin:.1rem 0 .5rem;">اگر شبکه‌ی اجتماعی‌تان در لیست بالا نیست، اسم آن و لینک کامل آن را اینجا وارد کنید.</p>
              <div class="profile-socials-grid">
                <div>
                  <label class="field-label">اسم شبکه اجتماعی</label>
                  <input type="text" id="profileCustomLabel" placeholder="مثلاً: بیهنس">
                </div>
                <div>
                  <label class="field-label">لینک کامل</label>
                  <input type="text" id="profileCustomUrl" placeholder="https://...">
                </div>
              </div>

              <button type="button" class="btn-primary" id="saveProfileBtn">ذخیره پروفایل</button>
              <div class="form-msg" id="profileMsg"></div>
            </div>
          </div>
        </div>

        <div class="settings-block">
          <h2>تغییر رمز عبور من</h2>
          <p class="settings-desc">برای امنیت حساب خود از یک رمز عبور قوی و منحصربه‌فرد استفاده کنید.</p>
          <form class="password-form" id="changePasswordForm">
            <div class="admin-form-row">
              <label class="field-label" for="curPasswordInput">رمز عبور فعلی</label>
              <input type="password" name="current_password" id="curPasswordInput" class="field-control" placeholder="رمز عبور فعلی خود را وارد کنید" required>
            </div>
            <div class="admin-form-row">
              <label class="field-label" for="newPasswordInput">رمز عبور جدید</label>
              <p class="field-desc">حداقل ۸ کاراکتر</p>
              <input type="password" name="new_password" id="newPasswordInput" class="field-control" placeholder="رمز عبور جدید را وارد کنید" required minlength="8">
            </div>
            <button type="submit" class="btn-primary">تغییر رمز عبور</button>
          </form>
          <div class="form-msg" id="changePasswordMsg"></div>
        </div>
      </div>

      <?php if ($canManageAdmins): ?>
      <div class="settings-subpanel" data-subtab-panel="admins">
        <div class="settings-block">
          <h2>مدیریت ادمین‌ها</h2>
          <p class="settings-desc">
            افزودن ادمین جدید و تعیین دسترسی‌های دقیق او.
            <?php if (!$isSuperAdmin): ?>توجه: شما اجازه ویرایش یا حذف سوپر ادمین را ندارید.<?php endif; ?>
          </p>

          <form class="admin-form-grid" id="addAdminForm">
            <!-- ردیف اول: اطلاعات پایه -->
            <div class="admin-form-row">
              <label class="field-label" for="newAdminUsername">نام کاربری</label>
              <p class="field-desc">نام کاربری منحصربه‌فرد برای ورود به پنل</p>
              <input type="text" id="newAdminUsername" name="username" class="field-control" placeholder="مثلاً: reza_ahmadi" required minlength="3" maxlength="60">
            </div>

            <div class="admin-form-row">
              <label class="field-label" for="newAdminPassword">رمز عبور</label>
              <p class="field-desc">حداقل ۸ کاراکتر شامل حروف و اعداد</p>
              <input type="password" id="newAdminPassword" name="password" class="field-control" placeholder="رمز عبور جدید" required minlength="8">
            </div>

            <div class="admin-form-row">
              <label class="field-label" for="newAdminRole">نقش ادمین</label>
              <p class="field-desc">انتخاب نقش پایه — دسترسی‌های دقیق‌تر را در بخش زیر تنظیم کنید</p>
              <?php if ($isSuperAdmin): ?>
              <select name="role" id="newAdminRole" class="field-control">
                <option value="admin">ادمین (دسترسی سفارشی)</option>
                <option value="super_admin">سوپر ادمین (دسترسی کامل)</option>
              </select>
              <?php else: ?>
              <input type="hidden" name="role" value="admin">
              <span style="color:var(--text-mute);font-size:0.85rem;">ادمین عادی</span>
              <?php endif; ?>
            </div>

            <!-- پنل دسترسی‌ها -->
            <div id="newAdminPermissionsWrap">
              <?= resa_permission_fields_inline('new_blog_scope') ?>
            </div>

            <button type="submit" class="btn-primary" id="addAdminSubmitBtn" style="align-self:flex-start;">افزودن ادمین</button>
          </form>
          <div class="form-msg" id="addAdminMsg"></div>

          <!-- جدول ادمین‌ها -->
          <div class="admins-table-wrap">
            <table class="admins-table" id="adminsTable">
              <thead>
                <tr><th>نام کاربری</th><th>نقش</th><th>تاریخ ساخت</th><th>عملیات</th></tr>
              </thead>
              <tbody><tr><td colspan="4" class="empty-state">در حال بارگذاری...</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($isSuperAdmin): ?>
      <div class="settings-subpanel" data-subtab-panel="team-cards">
        <div class="settings-block">
          <h2>کارت‌های بخش «تیم ما»</h2>
          <p class="settings-desc">هر ادمین از تنظیمات پروفایل خودش کارت تیم را می‌سازد؛ اینجا می‌توانید کارت هر عضو را ویرایش کنید، ترتیب نمایش را با دکمه‌های ▲▼ تغییر دهید، یا برای فردی که حساب ادمین ندارد کارت اضافه کنید.</p>

          <button type="button" class="btn-primary" id="addExtraMemberBtn" style="margin-bottom:1rem;">+ افزودن عضو بدون حساب ادمین</button>

          <div class="team-cards-list" id="teamCardsList">
            <p class="empty-state">در حال بارگذاری...</p>
          </div>
        </div>

        <div class="settings-block" id="teamCardEditorBlock" style="display:none;">
          <h2 id="teamCardEditorTitle">ویرایش کارت</h2>
          <form class="profile-form" id="teamCardEditorForm">
            <input type="hidden" id="tcType" value="extra">
            <input type="hidden" id="tcId" value="">
            <div class="profile-avatar-col">
              <div class="cover-drop" id="tcCardImageDrop" style="width:120px; height:120px; border-radius:22px;">
                <img id="tcCardImagePreview" alt="عکس کارت" style="display:none;">
                <span id="tcCardImagePlaceholder">عکس کارت</span>
              </div>
              <input type="file" id="tcCardImageInput" accept="image/jpeg,image/png,image/webp" style="display:none;">
              <button type="button" class="table-del-btn" id="tcRemoveCardImageBtn" style="margin-top:.6rem;">حذف عکس</button>
            </div>
            <div class="profile-fields-col">
              <label class="field-label" id="tcNameLabel">نام</label>
              <input type="text" id="tcName" maxlength="120" placeholder="نام و نام خانوادگی">
              <label class="field-label">عنوان شغلی</label>
              <input type="text" id="tcJobTitle" maxlength="160" placeholder="مثلاً: طراح رابط کاربری">
              <label class="field-label">بیوگرافی خلاصه</label>
              <textarea id="tcBioShort" rows="2" maxlength="240" placeholder="یک یا دو جمله کوتاه..."></textarea>

              <label class="field-label" style="display:flex; align-items:center; gap:.5rem;">
                <input type="checkbox" id="tcEnabled" checked style="width:auto;"> نمایش در بخش «تیم ما»
              </label>

              <div class="profile-socials-grid">
                <div><label class="field-label">تلگرام</label><input type="text" id="tcTelegram" placeholder="@username"></div>
                <div><label class="field-label">واتساپ</label><input type="text" id="tcWhatsapp" placeholder="989xxxxxxxxx"></div>
                <div><label class="field-label">اینستاگرام</label><input type="text" id="tcInstagram" placeholder="@username"></div>
                <div><label class="field-label">گیت‌هاب</label><input type="text" id="tcGithub" placeholder="username"></div>
                <div><label class="field-label">ایمیل</label><input type="email" id="tcEmail" placeholder="name@example.com"></div>
                <div><label class="field-label">شماره تماس</label><input type="text" id="tcPhone" placeholder="09xxxxxxxxx"></div>
                <div><label class="field-label">لینکدین</label><input type="text" id="tcLinkedin" placeholder="لینک پروفایل"></div>
                <div><label class="field-label">ایکس (X)</label><input type="text" id="tcX" placeholder="@username"></div>
                <div><label class="field-label">یوتیوب</label><input type="text" id="tcYoutube" placeholder="لینک کانال"></div>
                <div><label class="field-label">وبسایت</label><input type="text" id="tcWebsite" placeholder="example.com"></div>
                <div><label class="field-label">پینترست</label><input type="text" id="tcPinterest" placeholder="username"></div>
              </div>

              <label class="field-label" style="margin-top:.9rem;">شبکه اجتماعی دلخواه</label>
              <div class="profile-socials-grid">
                <div><label class="field-label">اسم شبکه اجتماعی</label><input type="text" id="tcCustomLabel" placeholder="مثلاً: بیهنس"></div>
                <div><label class="field-label">لینک کامل</label><input type="text" id="tcCustomUrl" placeholder="https://..."></div>
              </div>

              <div style="display:flex; gap:.7rem;">
                <button type="button" class="btn-primary" id="tcSaveBtn">ذخیره کارت</button>
                <button type="button" class="table-del-btn" id="tcCancelBtn">انصراف</button>
              </div>
              <div class="form-msg" id="teamCardEditorMsg"></div>
            </div>
          </form>
        </div>
      </div>

      <div class="settings-subpanel" data-subtab-panel="awards">
        <div class="settings-block">
          <h2>دستاوردی چشم‌نواز — ویترین افتخارات رسا</h2>
          <p class="settings-desc">تصاویر جام‌ها/جوایزی که در بخش «دستاوردی چشم‌نواز» صفحه خانه و درباره ما نمایش داده می‌شود را از اینجا مدیریت کنید. برای تغییر ترتیب، کارت‌ها را بکشید و رها کنید.</p>

          <form class="profile-form" id="awardAddForm" style="margin-bottom:1.4rem;">
            <div class="profile-avatar-col">
              <div class="cover-drop" id="awardImageDrop" style="width:120px; height:120px; border-radius:22px;">
                <img id="awardImagePreview" alt="عکس جایزه" style="display:none;">
                <span id="awardImagePlaceholder">عکس جایزه</span>
              </div>
              <input type="file" id="awardImageInput" accept="image/jpeg,image/png,image/webp" style="display:none;">
            </div>
            <div class="profile-fields-col">
              <label class="field-label">عنوان (اختیاری)</label>
              <input type="text" id="awardTitle" maxlength="160" placeholder="مثلاً: تندیس برند برتر ۱۴۰۳">
              <button type="button" class="btn-primary" id="awardAddBtn" style="align-self:flex-start; margin-top:.6rem;">+ افزودن جایزه</button>
              <div class="form-msg" id="awardAddMsg"></div>
            </div>
          </form>

          <div class="team-cards-list" id="awardsList">
            <p class="empty-state">در حال بارگذاری...</p>
          </div>
        </div>
      </div>

      <div class="settings-subpanel" data-subtab-panel="dev-team">
        <div class="settings-block">
          <h2>افراد توسعه دهنده</h2>
          <p class="settings-desc">مشخص کنید کدام ادمین‌ها در بخش «افراد توسعه دهنده» صفحه تماس با ما نمایش داده شوند و با چه ترتیبی. کارت هر ادمین (عکس، عنوان شغلی و شبکه‌های اجتماعی) از پروفایل خودش خوانده می‌شود. برای تغییر ترتیب، ادمین‌های فعال را بکشید و رها کنید.</p>

          <div class="team-cards-list" id="devTeamAdminsList">
            <p class="empty-state">در حال بارگذاری...</p>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($canManageSite): ?>
      <div class="settings-subpanel" data-subtab-panel="site">
        <div class="settings-block">
          <h2>تنظیمات نظرات کاربران</h2>
          <p class="settings-desc">نظرات جدید بدون تایید ادمین منتشر شوند یا در انتظار تایید بمانند؟</p>
          <label class="perm-check-inline">
            <span class="perm-label">انتشار خودکار نظرات <small>بدون نیاز به تایید ادمین</small></span>
            <input type="checkbox" id="commentsAutoApproveToggle">
          </label>
        </div>

        <div class="settings-block">
          <h2>حالت تعمیرات سایت</h2>
          <p class="settings-desc">به‌جای سایت، صفحه «در حال تعمیر» به بازدیدکنندگان نمایش داده می‌شود؛ پنل ادمین همچنان در دسترس شماست.</p>
          <label class="perm-check-inline maintenance-toggle-row">
            <span class="perm-label">فعال‌سازی حالت تعمیرات <small>برای همه بازدیدکنندگان سایت</small></span>
            <input type="checkbox" id="maintenanceModeToggle">
          </label>
          <div class="maintenance-status-note" id="maintenanceStatusNote" style="display:none;"></div>

          <label class="field-label" style="display:block; margin-top:1rem;">پیام نمایشی در صفحه تعمیرات (اختیاری)</label>
          <textarea id="maintenanceMessageInput" class="settings-textarea" rows="3" maxlength="600" placeholder="مثلاً: در حال انجام یک سری بروزرسانی هستیم، تا چند لحظه دیگر برمی‌گردیم."></textarea>

          <button type="button" class="btn-primary" id="saveMaintenanceBtn" style="margin-top:.9rem;">ذخیره تنظیمات تعمیرات</button>
          <div class="form-msg" id="maintenanceMsg"></div>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($canViewStats): ?>
      <div class="settings-subpanel" data-subtab-panel="stats">
        <div class="settings-block">
          <h2>آمار بازدید سایت</h2>
          <p class="settings-desc">آمار ساده بازدید صفحات سایت، بر اساس بارگذاری صفحه در مرورگر بازدیدکنندگان.</p>
          <div class="stat-cards-grid" id="visitStatsCards">
            <div class="empty-state">در حال بارگذاری آمار...</div>
          </div>
          <div class="visit-chart-wrap" id="visitChart"></div>
          <div class="top-pages-wrap" id="topPagesList"></div>
        </div>

        <div class="settings-block">
          <h2>گزارش فعالیت‌ها (لاگ)</h2>
          <p class="settings-desc">آخرین رویدادهای امنیتی و مدیریتی پنل ادمین: ورود و خروج، تغییر تنظیمات، مدیریت ادمین‌ها، تایید یا حذف نظرات و پیام‌ها.</p>
          <div class="activity-log-list" id="activityLogList">
            <div class="empty-state">در حال بارگذاری گزارش...</div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($canManageBackup): ?>
      <!-- ================= پشتیبان‌گیری ================= -->
      <div class="settings-subpanel" data-subtab-panel="backup">
        <div class="settings-block">
          <h2>پشتیبان‌گیری از دیتابیس و رسانه‌ها</h2>
          <p class="settings-desc">یک فایل ZIP شامل ساختار و اطلاعات جدول‌های انتخابی به‌همراه فایل‌های آپلودی (عکس‌ها و رسانه‌ها) بسازید و دانلود کنید. پشتیبان کامل، همه‌چیز شامل تمام فایل‌های آپلودی را در بر می‌گیرد.</p>

          <div class="backup-scope-choice">
            <button type="button" class="backup-scope-btn is-active" data-scope="full">پشتیبان کامل (همه‌چیز + فایل‌ها)</button>
            <button type="button" class="backup-scope-btn" data-scope="partial">انتخاب بخش‌های خاص</button>
          </div>

          <div class="backup-sections-grid" id="backupSectionsGrid" style="display:none;">
            <label class="backup-section-check"><input type="checkbox" value="messages"> پیام‌های تماس با ما</label>
            <label class="backup-section-check"><input type="checkbox" value="projects"> پروژه‌ها</label>
            <label class="backup-section-check"><input type="checkbox" value="articles"> وبلاگ‌ها و دسته‌بندی‌ها</label>
            <label class="backup-section-check"><input type="checkbox" value="comments"> نظرات کاربران</label>
            <label class="backup-section-check"><input type="checkbox" value="admins"> ادمین‌ها</label>
            <label class="backup-section-check"><input type="checkbox" value="settings"> تنظیمات سایت</label>
            <label class="backup-section-check"><input type="checkbox" value="logs"> آمار و گزارش فعالیت</label>
            <label class="backup-section-check"><input type="checkbox" value="uploads"> فایل‌های آپلودی (عکس‌ها و رسانه‌ها)</label>
          </div>

          <button type="button" class="btn-primary" id="downloadBackupBtn">دانلود فایل پشتیبان (ZIP)</button>
          <div class="form-msg" id="backupMsg"></div>
        </div>

        <div class="settings-block">
          <h2>پشتیبان‌گیری خودکار</h2>
          <p class="settings-desc">اگر فعال باشد، در بازدیدهای بعدی از پنل ادمین، به‌صورت خودکار (کامل + فایل‌ها) بک‌آپ گرفته و در سرور نگه‌داری می‌شود — همیشه فقط ۵ نسخه‌ی آخر باقی می‌ماند. آخرین نسخه‌ها را می‌توانید از بخش «سلامت سیستم» دانلود کنید.</p>
          <select class="settings-select" id="backupScheduleSelect">
            <option value="off">خاموش</option>
            <option value="daily">روزانه</option>
            <option value="weekly">هفتگی</option>
          </select>
          <div class="form-msg" id="backupScheduleMsg"></div>
        </div>

        <div class="settings-block">
          <h2>بازیابی از فایل پشتیبان</h2>
          <p class="settings-desc">یک فایل پشتیبان ZIP (خروجی همین بخش) یا فایل SQL خام را انتخاب کنید تا در دیتابیس بازیابی شود. <strong>توجه:</strong> این عملیات اطلاعات فعلی جدول‌های موجود در فایل را جایگزین می‌کند و غیرقابل بازگشت است؛ پیش از بازیابی حتماً از وضعیت فعلی یک پشتیبان تهیه کنید.</p>

          <form class="restore-backup-form" id="restoreBackupForm">
            <label class="file-drop" id="restoreFileDrop" for="restoreBackupFile">
              <span class="file-drop-icon">🗄️</span>
              <span class="file-drop-text">
                <strong>برای انتخاب فایل کلیک کنید</strong>
                <small id="restoreFileName">فایل ZIP یا SQL پشتیبان</small>
              </span>
              <input type="file" id="restoreBackupFile" accept=".sql,.zip" required hidden>
            </label>
            <button type="submit" class="btn-primary" style="background:linear-gradient(135deg,#e0453f,#b8322d);">بازیابی از فایل پشتیبان</button>
          </form>
          <div class="form-msg" id="restoreBackupMsg"></div>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($canViewHealth): ?>
      <div class="settings-subpanel" data-subtab-panel="health">
        <div class="settings-block">
          <h2>سلامت سیستم</h2>
          <p class="settings-desc">وضعیت لحظه‌ای دیتابیس، فضای سرور و آخرین پشتیبان‌ها.</p>
          <div class="stat-cards-grid" id="healthCards">
            <div class="empty-state">در حال بررسی سلامت سیستم...</div>
          </div>
        </div>

        <div class="settings-block">
          <h2>پشتیبان‌های موجود روی سرور</h2>
          <p class="settings-desc">۵ نسخه‌ی آخرِ پشتیبان (دستی و خودکار) که روی سرور نگه‌داری می‌شوند.</p>
          <div id="healthBackupsList"><div class="empty-state">در حال بارگذاری...</div></div>
        </div>
      </div>
      <?php endif; ?>

          </div>
        </div>
      </div>
    </section>
