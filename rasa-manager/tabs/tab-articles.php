    <section class="tab-panel <?= $defaultTab === 'articles' ? 'is-active' : '' ?>" id="tab-articles">
      <div class="panel-toolbar">
        <div class="stat-chip">تعداد کل: <b id="artTotalCount">۰</b></div>
        <div class="stat-chip stat-chip--accent">منتشر شده: <b id="artPublishedCount">۰</b></div>
        <div class="stat-chip">پیش‌نویس: <b id="artDraftCount">۰</b></div>
        <div class="toolbar-actions">
          <?php if ($canManageCategories): ?>
          <button class="btn-rect" id="manageCategoriesBtn" type="button"><?= $isSuperAdmin ? 'مدیریت دسته‌بندی‌ها' : 'افزودن دسته‌بندی' ?></button>
          <?php endif; ?>
          <button class="btn-primary" id="newArticleBtn" type="button">+ وبلاگ جدید</button>
        </div>
      </div>

      <div class="articles-filters">
        <input type="search" id="articleSearchInput" placeholder="جستجو در عنوان یا نویسنده...">
        <select id="articleCategoryFilter"><option value="">همه دسته‌بندی‌ها</option></select>
        <select id="articleStatusFilter">
          <option value="">همه وضعیت‌ها</option>
          <option value="published">منتشر شده</option>
          <option value="draft">پیش‌نویس</option>
        </select>
        <select id="articleAuthorFilter" style="display:none;"><option value="">همه نویسنده‌ها</option></select>
      </div>

      <div class="articles-table-wrap">
        <table class="articles-table" id="articlesTable">
          <thead>
            <tr>
              <th></th>
              <th>عنوان</th>
              <th>دسته‌بندی</th>
              <th>نویسنده</th>
              <th>وضعیت</th>
              <th>تاریخ انتشار</th>
              <th>بازدید</th>
              <th>عملیات</th>
            </tr>
          </thead>
          <tbody><tr><td colspan="8" class="empty-state">در حال بارگذاری وبلاگ‌ها...</td></tr></tbody>
        </table>
      </div>
      <div class="admin-pagination" id="articlesPagination"></div>
    </section>
