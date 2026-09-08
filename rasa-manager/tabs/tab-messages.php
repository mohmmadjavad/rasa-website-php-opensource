    <!-- ================= تب پیام‌ها ================= -->
    <section class="tab-panel <?= $defaultTab === 'messages' ? 'is-active' : '' ?>" id="tab-messages">
      <div class="panel-toolbar">
        <div class="stat-chip">تعداد کل: <b id="msgTotalCount">0</b></div>
        <div class="stat-chip stat-chip--accent">خوانده‌نشده: <b id="msgUnreadCount">0</b></div>
        <button class="btn-ghost" id="refreshMessagesBtn" type="button">بروزرسانی</button>
      </div>

      <div class="messages-list" id="messagesList">
        <div class="empty-state">در حال بارگذاری پیام‌ها...</div>
      </div>
      <div class="admin-pagination" id="messagesPagination"></div>
    </section>
