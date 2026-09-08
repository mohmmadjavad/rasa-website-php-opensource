    <section class="tab-panel <?= $defaultTab === 'comments' ? 'is-active' : '' ?>" id="tab-comments">
      <div class="panel-toolbar">
        <div class="stat-chip">تعداد کل: <b id="cmTotalCount">۰</b></div>
        <div class="stat-chip stat-chip--accent">در انتظار تایید: <b id="cmPendingCount">۰</b></div>
        <div class="stat-chip">تاییدشده: <b id="cmApprovedCount">۰</b></div>
        <button class="btn-ghost" id="refreshCommentsBtn" type="button">بروزرسانی</button>
      </div>

      <div class="articles-filters">
        <input type="search" id="commentSearchInput" placeholder="جستجو در متن نظر، نام کاربر یا عنوان وبلاگ...">
        <select id="commentStatusFilter">
          <option value="">همه وضعیت‌ها</option>
          <option value="pending">در انتظار تایید</option>
          <option value="approved">تاییدشده</option>
          <option value="rejected">رد شده</option>
        </select>
      </div>

      <div class="comments-list" id="commentsList">
        <div class="empty-state">در حال بارگذاری نظرات...</div>
      </div>
      <div class="admin-pagination" id="commentsPagination"></div>
    </section>
