    <!-- ================= تب داشبورد ================= -->
    <section class="tab-panel <?= $defaultTab === 'dashboard' ? 'is-active' : '' ?>" id="tab-dashboard">

      <div class="settings-block">
        <h2>نمای کلی</h2>
        <p class="settings-desc">خلاصه‌ی وضعیت محتوا، نظرات و پیام‌ها — بر اساس همان دسترسی‌هایی که برای شما تعریف شده.</p>
        <div class="stat-cards-grid" id="dashKpiGrid">
          <div class="empty-state">در حال بارگذاری آمار...</div>
        </div>
      </div>

      <div class="dash-columns">
        <div class="settings-block" id="dashArticlesCard" style="display:none;">
          <h2>پربازدیدترین وبلاگ‌ها</h2>
          <ul class="dash-top-list" id="dashTopArticles"></ul>
        </div>

        <div class="settings-block" id="dashProjectsCard" style="display:none;">
          <h2>پربازدیدترین پروژه‌ها</h2>
          <ul class="dash-top-list" id="dashTopProjects"></ul>
        </div>
      </div>

      <div class="settings-block" id="dashVisitsCard" style="display:none;">
        <h2>بازدید سایت</h2>
        <p class="settings-desc">این بخش فقط برای سوپر ادمین نمایش داده می‌شود.</p>
        <div class="visit-chart-wrap" id="dashVisitChart"></div>
      </div>

    </section>
