    <section class="tab-panel <?= $defaultTab === 'projects' ? 'is-active' : '' ?>" id="tab-projects">
      <div class="panel-toolbar">
        <div class="stat-chip">تعداد کل: <b id="projTotalCount">۰</b></div>
        <div class="stat-chip stat-chip--accent">منتشر شده: <b id="projPublishedCount">۰</b></div>
        <div class="stat-chip">پیش‌نویس: <b id="projDraftCount">۰</b></div>
        <div class="toolbar-actions">
          <?php if ($canManageProjectCategories): ?>
          <button class="btn-rect" id="manageProjectCategoriesBtn" type="button"><?= $isSuperAdmin ? 'مدیریت دسته‌بندی‌های پروژه' : 'افزودن دسته‌بندی پروژه' ?></button>
          <button class="btn-rect" id="manageBrandsBtn" type="button"><?= $isSuperAdmin ? 'مدیریت برندها' : 'افزودن برند' ?></button>
          <?php endif; ?>
          <button class="btn-primary" id="newProjectBtn" type="button">+ پروژه جدید</button>
        </div>
      </div>

      <div class="articles-filters">
        <input type="search" id="projectSearchInput" placeholder="جستجو در عنوان پروژه...">
        <select id="projectCategoryFilter"><option value="">همه دسته‌بندی‌ها</option></select>
        <select id="projectStatusFilter">
          <option value="">همه وضعیت‌ها</option>
          <option value="published">منتشر شده</option>
          <option value="draft">پیش‌نویس</option>
        </select>
      </div>

      <div class="projects-grid-admin" id="projectsGridAdmin">
        <div class="empty-state">در حال بارگذاری پروژه‌ها...</div>
      </div>
    </section>
