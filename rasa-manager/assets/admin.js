/* =========================================================
   admin.js — پنل ادمین رسا
   ========================================================= */

   (function () {
    "use strict";
  
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || "";
    const canDeleteMessages = document.querySelector('meta[name="can-delete-messages"]')?.content === "1";
    const tabTitles = {
      messages: "پیام‌های تماس با ما",
      projects: "مدیریت پروژه‌ها",
      articles: "مدیریت وبلاگ‌ها",
      comments: "نظرات کاربران",
      email: "ایمیل",
      settings: "تنظیمات",
    };
    const ROLE_LABELS = {
      super_admin: "سوپر ادمین",
      admin: "ادمین",
    };
  
    function roleLabel(role) {
      return ROLE_LABELS[role] || ROLE_LABELS.admin;
    }
  
    document.addEventListener("DOMContentLoaded", () => {
      const steps = [
        ["initSidebarTabs", initSidebarTabs],
        ["initMobileMenu", initMobileMenu],
        ["initSettingsSubtabs", initSettingsSubtabs],
        ["loadMessages", loadMessages],
        ["loadAdmins", loadAdmins],
        ["initAddAdminForm", initAddAdminForm],
        ["initEditAdminModal", initEditAdminModal],
        ["initPermRadioPills", initPermRadioPills],
        ["initChangePasswordForm", initChangePasswordForm],
        ["initModal", initModal],
        ["initProfileSettings", initProfileSettings],
        ["initTeamCardsSettings", initTeamCardsSettings],
        ["initAwardsSettings", initAwardsSettings],
        ["initDevTeamSettings", initDevTeamSettings],
        ["initWebmailControls", initWebmailControls],
      ];
      steps.forEach(([name, fn]) => {
        try {
          fn();
        } catch (err) {
          console.error("[admin] " + name + " failed:", err);
        }
      });
    });
  
    /* ---------------------------------------------------------
       Toast helper
    --------------------------------------------------------- */
    function showToast(message, isError) {
      const toast = document.getElementById("toast");
      if (!toast) return;
      toast.textContent = message;
      toast.classList.toggle("is-error", !!isError);
      toast.classList.add("is-visible");
      clearTimeout(showToast._t);
      showToast._t = setTimeout(() => toast.classList.remove("is-visible"), 3200);
    }
  
    /* ---------------------------------------------------------
       API helper
    --------------------------------------------------------- */
    async function apiCall(url, body) {
      const opts = {
        method: body ? "POST" : "GET",
        headers: { "X-CSRF-Token": csrfToken },
      };
      if (body) {
        opts.headers["Content-Type"] = "application/json";
        opts.body = JSON.stringify(body);
      }
      const res = await fetch(url, opts);
      let data;
      try {
        data = await res.json();
      } catch (e) {
        throw new Error("پاسخ نامعتبر از سرور دریافت شد.");
      }
      if (!res.ok && !data) throw new Error("خطای ارتباط با سرور.");
      return data;
    }
  
    /* ---------------------------------------------------------
       Sidebar tabs
    --------------------------------------------------------- */
    function initSidebarTabs() {
      const tabs = document.querySelectorAll(".side-tab");
      const panels = document.querySelectorAll(".tab-panel");
      const title = document.getElementById("topbarTitle");
      const sidebar = document.querySelector(".admin-sidebar");
  
      tabs.forEach((tab) => {
        tab.addEventListener("click", () => {
          tabs.forEach((t) => t.classList.remove("is-active"));
          tab.classList.add("is-active");
          const target = tab.dataset.tab;
          panels.forEach((p) => p.classList.toggle("is-active", p.id === "tab-" + target));
          if (title) title.textContent = tabTitles[target] || "";
          sidebar.classList.remove("is-open");
          document.getElementById("sidebarBackdrop")?.classList.remove("is-open");
          if (target === "email") loadWebmail();
        });
      });

      // اگر تب پیش‌فرض همین لحظه «ایمیل» است (مثلاً کاربری که فقط دسترسی ایمیل دارد)
      if (document.querySelector('.side-tab.is-active')?.dataset.tab === "email") {
        loadWebmail();
      }
    }

    /* ---------------------------------------------------------
       تب ایمیل — بارگذاری Roundcube داخل iframe با ورود خودکار (SSO)
    --------------------------------------------------------- */
    let webmailLoaded = false;
    async function loadWebmail(forceReload) {
      const frame = document.getElementById("webmailFrame");
      if (!frame) return; // یعنی صندوق ایمیل هنوز ساخته نشده (حالت empty-state)
      if (webmailLoaded && !forceReload) return;

      try {
        const data = await apiCall("api/webmail_sso_url.php");
        if (!data.ok) throw new Error(data.message);
        frame.src = data.data.url;
        webmailLoaded = true;
        const newTabLink = document.getElementById("openWebmailNewTabBtn");
        if (newTabLink) newTabLink.href = data.data.url;
      } catch (err) {
        frame.replaceWith(Object.assign(document.createElement("div"), {
          className: "empty-state",
          textContent: err.message || "خطا در اتصال به وبمیل.",
        }));
      }
    }

    function initWebmailControls() {
      document.getElementById("reloadWebmailBtn")?.addEventListener("click", () => loadWebmail(true));
    }
  
    /* ---------------------------------------------------------
       Settings — iOS-style list / drill-down navigation
    --------------------------------------------------------- */
    const settingsRowTitles = {
      profile: "پروفایل و امنیت",
      admins: "ادمین‌ها",
      "team-cards": "کارت اعضای تیم",
      awards: "دستاوردها و جوایز",
      "dev-team": "افراد توسعه دهنده",
      site: "سایت",
      stats: "آمار و گزارش",
      backup: "پشتیبان‌گیری",
      health: "سلامت سیستم",
    };

    function initSettingsSubtabs() {
      const wrap = document.getElementById("iosSettings");
      const rows = document.querySelectorAll(".ios-row[data-subtab]");
      const panels = document.querySelectorAll(".settings-subpanel");
      const backBtn = document.getElementById("settingsBackBtn");
      const detailTitle = document.getElementById("settingsDetailTitle");
      if (!wrap || !rows.length) return;

      function openSubtab(target) {
        panels.forEach((p) => p.classList.toggle("is-active", p.dataset.subtabPanel === target));
        if (detailTitle) detailTitle.textContent = settingsRowTitles[target] || "";
        wrap.classList.add("is-drilled");
        document.dispatchEvent(new CustomEvent("settings-subtab-open", { detail: { target } }));
        wrap.scrollIntoView({ behavior: "smooth", block: "start" });
      }

      rows.forEach((row) => {
        row.addEventListener("click", () => openSubtab(row.dataset.subtab));
      });

      backBtn?.addEventListener("click", () => {
        wrap.classList.remove("is-drilled");
      });
    }
  
    function initMobileMenu() {
      const btn = document.getElementById("mobileMenuBtn");
      const sidebar = document.querySelector(".admin-sidebar");
      const backdrop = document.getElementById("sidebarBackdrop");
      if (!btn || !sidebar) return;
  
      function closeSidebar() {
        sidebar.classList.remove("is-open");
        backdrop?.classList.remove("is-open");
      }
      function toggleSidebar() {
        sidebar.classList.toggle("is-open");
        backdrop?.classList.toggle("is-open");
      }
  
      btn.addEventListener("click", toggleSidebar);
      backdrop?.addEventListener("click", closeSidebar);
      document.addEventListener("click", (e) => {
        if (sidebar.classList.contains("is-open") && !sidebar.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
          closeSidebar();
        }
      });
    }
  
    /* ---------------------------------------------------------
       Messages tab
    --------------------------------------------------------- */
    let messagesCache = [];
    let currentMessagesPage = 1;
  
    async function loadMessages(page) {
      if (page) currentMessagesPage = page;
      const listEl = document.getElementById("messagesList");
      if (!listEl) return;
      try {
        const data = await apiCall("api/messages_list.php?page=" + currentMessagesPage);
        if (!data.ok) throw new Error(data.message || "خطا در دریافت پیام‌ها.");
  
        messagesCache = data.data.messages;
        renderMessages(messagesCache);
        renderMessagesPagination(data.data);
        updateUnreadUI(data.data.unread_count, data.data.total_count);
      } catch (err) {
        listEl.innerHTML = '<div class="empty-state">خطا در بارگذاری پیام‌ها. صفحه را رفرش کنید.</div>';
      }
    }
  
    function renderMessagesPagination(data) {
      const el = document.getElementById("messagesPagination");
      if (!el) return;
      const page = data.page || 1;
      const totalPages = data.total_pages || 1;
      if (totalPages <= 1) {
        el.innerHTML = "";
        return;
      }
      let html = `<button class="admin-pagination-btn" data-page="${page - 1}" ${page <= 1 ? "disabled" : ""}>قبلی</button>`;
      const start = Math.max(1, page - 2);
      const end = Math.min(totalPages, page + 2);
      if (start > 1) {
        html += `<button class="admin-pagination-btn" data-page="1">۱</button>`;
        if (start > 2) html += `<span class="admin-pagination-info">…</span>`;
      }
      for (let i = start; i <= end; i++) {
        html += `<button class="admin-pagination-btn ${i === page ? "is-active" : ""}" data-page="${i}">${i.toLocaleString("fa-IR")}</button>`;
      }
      if (end < totalPages) {
        if (end < totalPages - 1) html += `<span class="admin-pagination-info">…</span>`;
        html += `<button class="admin-pagination-btn" data-page="${totalPages}">${totalPages.toLocaleString("fa-IR")}</button>`;
      }
      html += `<button class="admin-pagination-btn" data-page="${page + 1}" ${page >= totalPages ? "disabled" : ""}>بعدی</button>`;
      el.innerHTML = html;
      el.querySelectorAll("[data-page]").forEach((btn) => {
        btn.addEventListener("click", () => loadMessages(parseInt(btn.dataset.page, 10)));
      });
    }
  
    function updateUnreadUI(unread, total) {
      const badge = document.getElementById("sidebarUnreadBadge");
      const unreadEl = document.getElementById("msgUnreadCount");
      const totalEl = document.getElementById("msgTotalCount");
      if (unreadEl) unreadEl.textContent = unread;
      if (totalEl) totalEl.textContent = total;
      if (badge) {
        badge.textContent = unread;
        badge.style.display = unread > 0 ? "flex" : "none";
      }
    }
  
    function renderMessages(list) {
      const listEl = document.getElementById("messagesList");
      if (!list.length) {
        listEl.innerHTML = '<div class="empty-state">هنوز پیامی دریافت نشده است.</div>';
        return;
      }
      listEl.innerHTML = list.map((m) => messageCardHtml(m)).join("");
  
      listEl.querySelectorAll(".message-card").forEach((card) => {
        card.addEventListener("click", (e) => {
          if (e.target.closest(".icon-btn")) return;
          openMessageModal(parseInt(card.dataset.id, 10));
        });
      });
      listEl.querySelectorAll("[data-action='toggle-read']").forEach((btn) => {
        btn.addEventListener("click", (e) => {
          e.stopPropagation();
          toggleRead(parseInt(btn.dataset.id, 10), btn.dataset.next === "1");
        });
      });
      listEl.querySelectorAll("[data-action='delete']").forEach((btn) => {
        btn.addEventListener("click", (e) => {
          e.stopPropagation();
          deleteMessage(parseInt(btn.dataset.id, 10));
        });
      });
    }
  
    function escapeHtml(str) {
      const div = document.createElement("div");
      div.textContent = str ?? "";
      return div.innerHTML;
    }
  
    function formatDate(isoLike) {
      try {
        const d = new Date(isoLike.replace(" ", "T"));
        return d.toLocaleString("fa-IR", { dateStyle: "medium", timeStyle: "short" });
      } catch (e) {
        return isoLike;
      }
    }
  
    function messageCardHtml(m) {
      const unreadClass = m.is_read ? "" : "is-unread";
      const nextRead = m.is_read ? "0" : "1";
      const readIcon = m.is_read
        ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12s3.5-7 9-7 9 7 9 7-3.5 7-9 7-9-7-9-7Z"/><circle cx="12" cy="12" r="3"/></svg>'
        : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 5.5 20.5 18.5"/><path d="M9.9 6.6C10.6 6.2 11.3 6 12 6c5.5 0 9 6 9 6a15 15 0 0 1-3.1 3.6M6.5 8C4.4 9.5 3 12 3 12s3.5 6 9 6c1 0 2-.2 2.9-.5"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>';
  
      return `
        <div class="message-card ${unreadClass}" data-id="${m.id}">
          <div class="message-main">
            <div class="message-name">${escapeHtml(m.full_name)}</div>
            <div class="message-contact">${escapeHtml(m.contact_info)}</div>
            <div class="message-preview">${escapeHtml(m.message)}</div>
            <div class="message-date">${formatDate(m.created_at)}</div>
          </div>
          <div class="message-actions">
            <button class="icon-btn" data-action="toggle-read" data-id="${m.id}" data-next="${nextRead}" title="${m.is_read ? "علامت‌گذاری به‌عنوان نخوانده" : "علامت‌گذاری به‌عنوان خوانده‌شده"}">${readIcon}</button>
            ${
              canDeleteMessages
                ? `<button class="icon-btn icon-btn--danger" data-action="delete" data-id="${m.id}" title="حذف پیام">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M9 7V4.5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1V7"/><path d="M6 7l1 13a1.5 1.5 0 0 0 1.5 1.4h7a1.5 1.5 0 0 0 1.5-1.4L18 7"/></svg>
            </button>`
                : ""
            }
          </div>
        </div>`;
    }
  
    async function toggleRead(id, nextRead) {
      try {
        const data = await apiCall("api/messages_mark_read.php", { id, read: nextRead });
        if (!data.ok) throw new Error(data.message);
        await loadMessages();
      } catch (err) {
        showToast(err.message || "خطا در بروزرسانی پیام.", true);
      }
    }
  
    async function deleteMessage(id) {
      if (!confirm("آیا از حذف این پیام مطمئن هستید؟")) return;
      try {
        const data = await apiCall("api/messages_delete.php", { id });
        if (!data.ok) throw new Error(data.message);
        showToast("پیام حذف شد.");
        await loadMessages();
        closeModal();
      } catch (err) {
        showToast(err.message || "خطا در حذف پیام.", true);
      }
    }
  
    document.getElementById && document.addEventListener("DOMContentLoaded", () => {
      const refreshBtn = document.getElementById("refreshMessagesBtn");
      if (refreshBtn) refreshBtn.addEventListener("click", () => loadMessages());
    });
  
    /* ---------------------------------------------------------
       Modal
    --------------------------------------------------------- */
    function initModal() {
      const closeBtn = document.getElementById("modalCloseBtn");
      const backdrop = document.getElementById("messageModal");
      if (closeBtn) closeBtn.addEventListener("click", closeModal);
      if (backdrop) {
        backdrop.addEventListener("click", (e) => {
          if (e.target === backdrop) closeModal();
        });
      }
    }
  
    function openMessageModal(id) {
      const m = messagesCache.find((x) => x.id === id);
      if (!m) return;
      document.getElementById("modalSenderName").textContent = m.full_name;
      document.getElementById("modalMeta").textContent = m.contact_info + " — " + formatDate(m.created_at);
      document.getElementById("modalMessageText").textContent = m.message;
      document.getElementById("messageModal").classList.add("is-open");
      if (!m.is_read) toggleRead(id, true);
    }
  
    function closeModal() {
      document.getElementById("messageModal").classList.remove("is-open");
    }
  
    /* ---------------------------------------------------------
       Settings: admins management
    --------------------------------------------------------- */
    let adminsListCache = [];
    const viewerRole = document.querySelector('meta[name="admin-role"]')?.content || "admin";
    const viewerIsSuperAdmin = viewerRole === "super_admin";
  
    async function loadAdmins() {
      const tbody = document.querySelector("#adminsTable tbody");
      if (!tbody) return;
      try {
        const data = await apiCall("api/admins_list.php");
        if (!data.ok) throw new Error(data.message);
        adminsListCache = data.data.admins;
        renderAdmins(adminsListCache);
      } catch (err) {
        tbody.innerHTML = '<tr><td colspan="4" class="empty-state">خطا در بارگذاری فهرست ادمین‌ها.</td></tr>';
      }
    }
  
    function renderAdmins(admins) {
      const tbody = document.querySelector("#adminsTable tbody");
      if (!admins.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="empty-state">ادمینی یافت نشد.</td></tr>';
        return;
      }
      tbody.innerHTML = admins
        .map((a) => {
          const isProtected = a.role === "super_admin" && !viewerIsSuperAdmin;
          const actionsHtml = isProtected
            ? '<span class="perm-locked-note">فقط سوپر ادمین</span>'
            : `<button class="btn-ghost--sm" data-edit-id="${a.id}">ویرایش</button>
               <button class="table-del-btn" data-id="${a.id}" ${a.is_you ? "disabled" : ""}>حذف</button>`;
          return `
        <tr>
          <td>${escapeHtml(a.display_name || a.username)}${a.is_you ? '<span class="you-tag">شما</span>' : ""}</td>
          <td><span class="role-badge role-badge--${a.role}">${roleLabel(a.role)}</span></td>
          <td>${formatDate(a.created_at)}</td>
          <td class="admins-table-actions">${actionsHtml}</td>
        </tr>`;
        })
        .join("");
  
      tbody.querySelectorAll(".table-del-btn").forEach((btn) => {
        btn.addEventListener("click", () => deleteAdmin(parseInt(btn.dataset.id, 10)));
      });
      tbody.querySelectorAll("[data-edit-id]").forEach((btn) => {
        btn.addEventListener("click", () => openEditAdminModal(parseInt(btn.dataset.editId, 10)));
      });
    }
  
    /* ---------------------------------------------------------
       Settings: edit any admin's username/password/permissions
    --------------------------------------------------------- */
    function openEditAdminModal(id) {
      const modal = document.getElementById("editAdminModal");
      if (!modal) return;
      const admin = adminsListCache.find((a) => a.id === id);
      if (!admin) return;
  
      document.getElementById("editAdminId").value = id;
      document.getElementById("editAdminUsername").value = "";
      document.getElementById("editAdminPassword").value = "";
      document.getElementById("editAdminTarget").textContent = `ویرایش «${admin.display_name || admin.username}» — فیلد خالی تغییر نمی‌کند.`;
      document.getElementById("editAdminMsg").textContent = "";
      document.getElementById("editAdminMsg").className = "form-msg";
  
      const permWrap = document.getElementById("editAdminPermissionsWrap");
      if (admin.role === "super_admin") {
        if (permWrap) permWrap.style.display = "none";
      } else {
        if (permWrap) {
          permWrap.style.display = "block";
          fillPermissions(permWrap, admin.permissions);
        }
      }
  
      modal.classList.add("is-open");
    }
  
    function initEditAdminModal() {
      const modal = document.getElementById("editAdminModal");
      const closeBtn = document.getElementById("editAdminCloseBtn");
      const form = document.getElementById("editAdminForm");
      if (!modal || !form) return;
  
      closeBtn?.addEventListener("click", () => modal.classList.remove("is-open"));
      modal.addEventListener("click", (e) => {
        if (e.target === modal) modal.classList.remove("is-open");
      });
  
      form.addEventListener("submit", async (e) => {
        e.preventDefault();
        const msgEl = document.getElementById("editAdminMsg");
        msgEl.textContent = "";
        msgEl.className = "form-msg";
  
        const id = parseInt(document.getElementById("editAdminId").value, 10);
        const username = document.getElementById("editAdminUsername").value.trim();
        const password = document.getElementById("editAdminPassword").value;
        const admin = adminsListCache.find((a) => a.id === id);
        const permWrap = document.getElementById("editAdminPermissionsWrap");
        const includePermissions = admin && admin.role !== "super_admin";
  
        if (!username && !password && !includePermissions) {
          msgEl.textContent = "حداقل یکی از فیلدها را پر کنید.";
          msgEl.classList.add("is-error");
          return;
        }
  
        const payload = { id, username, password };
        if (includePermissions) {
          payload.permissions = collectPermissions(permWrap);
        }
  
        try {
          const data = await apiCall("api/admins_update.php", payload);
          if (!data.ok) throw new Error(data.message);
          showToast(data.message || "اطلاعات ادمین بروزرسانی شد.");
          modal.classList.remove("is-open");
          await loadAdmins();
        } catch (err) {
          msgEl.textContent = err.message || "خطا در بروزرسانی اطلاعات ادمین.";
          msgEl.classList.add("is-error");
        }
      });
    }
  
    async function deleteAdmin(id) {
      if (!confirm("آیا از حذف این ادمین مطمئن هستید؟")) return;
      try {
        const data = await apiCall("api/admins_delete.php", { id });
        if (!data.ok) throw new Error(data.message);
        showToast("ادمین حذف شد.");
        await loadAdmins();
      } catch (err) {
        showToast(err.message || "خطا در حذف ادمین.", true);
      }
    }
  
    /* ---------------------------------------------------------
       Permission-panel helpers
    --------------------------------------------------------- */
    /**
     * دکمه‌های «فقط خودش / همه» (پروژه، وبلاگ‌ها، نظرات) رادیو هستند
     * ولی خودشان مخفی‌اند و رنگ فقط با کلاس is-active روی لیبل اعمال می‌شود.
     * این تابع کلاس is-active را با وضعیت واقعی رادیوی انتخاب‌شده هماهنگ می‌کند
     * تا مشخص باشد کدام گزینه انتخاب شده.
     */
    function syncRadioGroup(group) {
      if (!group) return;
      group.querySelectorAll(".perm-radio-option").forEach((label) => {
        const input = label.querySelector('input[type="radio"]');
        label.classList.toggle("is-active", !!input?.checked);
      });
    }

    function syncAllPermRadioGroups(container) {
      if (!container) return;
      const groups =
        container.classList && container.classList.contains("perm-radio-inline")
          ? [container]
          : container.querySelectorAll(".perm-radio-inline");
      groups.forEach(syncRadioGroup);
    }

    function initPermRadioPills() {
      document.addEventListener("change", (e) => {
        const input = e.target;
        if (input && input.matches && input.matches('.perm-radio-inline input[type="radio"]')) {
          syncRadioGroup(input.closest(".perm-radio-inline"));
        }
      });
    }

    function collectPermissions(container) {
      if (!container) return null;
      const chk = (sel) => !!container.querySelector(`[data-perm="${sel}"]`)?.checked;
      const scopeRadio = container.querySelector('input[data-perm="blog.scope"]:checked');
      const projectsScopeRadio = container.querySelector('input[data-perm="projects.scope"]:checked');
      const commentsScopeRadio = container.querySelector('input[data-perm="comments.scope"]:checked');
      return {
        dashboard: { access: chk("dashboard.access") },
        messages: { view: chk("messages.view"), delete: chk("messages.delete") },
        projects: {
          access: chk("projects.access"),
          scope: projectsScopeRadio ? projectsScopeRadio.value : "own",
          manage_categories: chk("projects.manage_categories"),
        },
        blog: {
          access: chk("blog.access"),
          scope: scopeRadio ? scopeRadio.value : "own",
          manage_categories: chk("blog.manage_categories"),
        },
        comments: {
          access: chk("comments.access"),
          scope: commentsScopeRadio ? commentsScopeRadio.value : "own",
        },
        settings: {
          manage_admins: chk("settings.manage_admins"),
          manage_site: chk("settings.manage_site"),
          view_stats: chk("settings.view_stats"),
          manage_backup: chk("settings.manage_backup"),
          view_health: chk("settings.view_health"),
        },
      };
    }
  
    function fillPermissions(container, perms) {
      if (!container || !perms) return;
      const set = (sel, val) => {
        const el = container.querySelector(`[data-perm="${sel}"]`);
        if (el) el.checked = !!val;
      };
      set("dashboard.access", perms.dashboard?.access);
      set("messages.view", perms.messages?.view);
      set("messages.delete", perms.messages?.delete);
      set("projects.access", perms.projects?.access);
      set("projects.manage_categories", perms.projects?.manage_categories);
      const projectsScope = perms.projects?.scope === "all" ? "all" : "own";
      const projectsRadio = container.querySelector(`input[data-perm="projects.scope"][value="${projectsScope}"]`);
      if (projectsRadio) projectsRadio.checked = true;
      set("blog.access", perms.blog?.access);
      set("blog.manage_categories", perms.blog?.manage_categories);
      const scope = perms.blog?.scope === "all" ? "all" : "own";
      const radio = container.querySelector(`input[data-perm="blog.scope"][value="${scope}"]`);
      if (radio) radio.checked = true;
      set("comments.access", perms.comments?.access);
      const commentsScope = perms.comments?.scope === "all" ? "all" : "own";
      const commentsRadio = container.querySelector(`input[data-perm="comments.scope"][value="${commentsScope}"]`);
      if (commentsRadio) commentsRadio.checked = true;
      set("settings.manage_admins", perms.settings?.manage_admins);
      set("settings.manage_site", perms.settings?.manage_site);
      set("settings.view_stats", perms.settings?.view_stats);
      set("settings.manage_backup", perms.settings?.manage_backup);
      set("settings.view_health", perms.settings?.view_health);
      syncAllPermRadioGroups(container);
    }
  
    function defaultNewAdminPermissions() {
      return {
        dashboard: { access: true },
        messages: { view: false, delete: false },
        projects: { access: false, scope: "own", manage_categories: false },
        blog: { access: true, scope: "own", manage_categories: false },
        comments: { access: false, scope: "own" },
        settings: { manage_admins: false, manage_site: false, view_stats: false, manage_backup: false, view_health: false },
      };
    }
  
    /* =========================================================
       فرم افزودن ادمین
       ========================================================= */
    function initAddAdminForm() {
      const form = document.getElementById("addAdminForm");
      const msgEl = document.getElementById("addAdminMsg");
      if (!form) return;
  
      const roleSelect = document.getElementById("newAdminRole");
      const permWrap = document.getElementById("newAdminPermissionsWrap");
  
      if (permWrap) {
        fillPermissions(permWrap, defaultNewAdminPermissions());
      }
  
      function toggleRolePanels() {
        if (!roleSelect || !permWrap) return;
        const isSuper = roleSelect.value === "super_admin";
        permWrap.style.display = isSuper ? "none" : "block";
      }
  
      roleSelect?.addEventListener("change", toggleRolePanels);
      toggleRolePanels();
  
      form.addEventListener("submit", async (e) => {
        e.preventDefault();
        msgEl.textContent = "";
        msgEl.className = "form-msg";
  
        const username = form.username.value.trim();
        const password = form.password.value;
        const role = roleSelect ? roleSelect.value : "admin";
  
        if (!username || username.length < 3) {
          msgEl.textContent = "نام کاربری باید حداقل ۳ کاراکتر باشد.";
          msgEl.classList.add("is-error");
          return;
        }
        if (!password || password.length < 8) {
          msgEl.textContent = "رمز عبور باید حداقل ۸ کاراکتر باشد.";
          msgEl.classList.add("is-error");
          return;
        }
  
        const payload = { username, password, role };
        if (role !== "super_admin") {
          payload.permissions = collectPermissions(permWrap);
        }
  
        try {
          const data = await apiCall("api/admins_add.php", payload);
          if (!data.ok) throw new Error(data.message);
          const mail = data.data && data.data.mail;
          if (mail && mail.ok) {
            msgEl.textContent = (data.message || "ادمین با موفقیت اضافه شد.") + " ایمیل " + mail.email + " هم ساخته شد.";
            msgEl.classList.add("is-success");
          } else if (mail && !mail.ok) {
            msgEl.textContent = (data.message || "ادمین اضافه شد.") + " اما ساخت ایمیل ناموفق بود: " + mail.message;
            msgEl.classList.add("is-warning");
          } else {
            msgEl.textContent = data.message || "ادمین با موفقیت اضافه شد.";
            msgEl.classList.add("is-success");
          }
          form.reset();
          if (permWrap) {
            fillPermissions(permWrap, defaultNewAdminPermissions());
          }
          toggleRolePanels();
          await loadAdmins();
        } catch (err) {
          msgEl.textContent = err.message || "خطا در افزودن ادمین.";
          msgEl.classList.add("is-error");
        }
      });
    }
  
    /* ---------------------------------------------------------
       تغییر رمز عبور
    --------------------------------------------------------- */
    function initChangePasswordForm() {
      const form = document.getElementById("changePasswordForm");
      const msgEl = document.getElementById("changePasswordMsg");
      if (!form) return;
      form.addEventListener("submit", async (e) => {
        e.preventDefault();
        msgEl.textContent = "";
        msgEl.className = "form-msg";
        const current_password = form.current_password.value;
        const new_password = form.new_password.value;
        try {
          const data = await apiCall("api/change_password.php", { current_password, new_password });
          if (!data.ok) throw new Error(data.message);
          msgEl.textContent = data.message || "رمز عبور تغییر کرد.";
          msgEl.classList.add("is-success");
          form.reset();
        } catch (err) {
          msgEl.textContent = err.message || "خطا در تغییر رمز عبور.";
          msgEl.classList.add("is-error");
        }
      });
    }
  
    /* ---------------------------------------------------------
       Settings: my profile (self-service, any role)
    --------------------------------------------------------- */
    let profileAvatarFile = null;
    let profileRemoveAvatarFlag = false;
    let profileCardImageFile = null;
    let profileRemoveCardImageFlag = false;

    function initProfileSettings() {
      const saveBtn = document.getElementById("saveProfileBtn");
      if (!saveBtn) return;

      loadProfile();

      const drop = document.getElementById("profileAvatarDrop");
      const fileInput = document.getElementById("profileAvatarInput");
      drop?.addEventListener("click", () => fileInput.click());
      fileInput?.addEventListener("change", () => {
        const file = fileInput.files[0];
        if (!file) return;
        if (file.size > 6 * 1024 * 1024) {
          showToast("حجم عکس نباید بیشتر از ۶ مگابایت باشد.", true);
          return;
        }
        profileAvatarFile = file;
        profileRemoveAvatarFlag = false;
        const reader = new FileReader();
        reader.onload = (e) => {
          const img = document.getElementById("profileAvatarPreview");
          img.src = e.target.result;
          img.style.display = "block";
          document.getElementById("profileAvatarPlaceholder").style.display = "none";
        };
        reader.readAsDataURL(file);
      });

      document.getElementById("removeProfileAvatarBtn")?.addEventListener("click", () => {
        profileAvatarFile = null;
        profileRemoveAvatarFlag = true;
        fileInput.value = "";
        document.getElementById("profileAvatarPreview").style.display = "none";
        document.getElementById("profileAvatarPlaceholder").style.display = "block";
      });

      const cardDrop = document.getElementById("profileCardImageDrop");
      const cardInput = document.getElementById("profileCardImageInput");
      cardDrop?.addEventListener("click", () => cardInput.click());
      cardInput?.addEventListener("change", () => {
        const file = cardInput.files[0];
        if (!file) return;
        if (file.size > 6 * 1024 * 1024) {
          showToast("حجم عکس نباید بیشتر از ۶ مگابایت باشد.", true);
          return;
        }
        profileCardImageFile = file;
        profileRemoveCardImageFlag = false;
        const reader = new FileReader();
        reader.onload = (e) => {
          const img = document.getElementById("profileCardImagePreview");
          img.src = e.target.result;
          img.style.display = "block";
          document.getElementById("profileCardImagePlaceholder").style.display = "none";
        };
        reader.readAsDataURL(file);
      });

      document.getElementById("removeProfileCardImageBtn")?.addEventListener("click", () => {
        profileCardImageFile = null;
        profileRemoveCardImageFlag = true;
        cardInput.value = "";
        document.getElementById("profileCardImagePreview").style.display = "none";
        document.getElementById("profileCardImagePlaceholder").style.display = "block";
      });

      saveBtn.addEventListener("click", saveProfile);
    }

    async function loadProfile() {
      try {
        const data = await apiCall("api/profile_get.php");
        if (!data.ok) throw new Error(data.message);
        const p = data.data.admin;
        document.getElementById("profileDisplayName").value = p.display_name || "";
        document.getElementById("profileJobTitle").value = p.job_title || "";
        document.getElementById("profileBioShort").value = p.bio_short || "";
        document.getElementById("profileBioFull").value = p.bio_full || "";
        document.getElementById("profileTelegram").value = p.social_telegram || "";
        document.getElementById("profileWhatsapp").value = p.social_whatsapp || "";
        document.getElementById("profileInstagram").value = p.social_instagram || "";
        document.getElementById("profileGithub").value = p.social_github || "";
        document.getElementById("profileEmail").value = p.social_email || "";
        document.getElementById("profilePhone").value = p.social_phone || "";
        document.getElementById("profileLinkedin").value = p.social_linkedin || "";
        document.getElementById("profileX").value = p.social_x || "";
        document.getElementById("profileYoutube").value = p.social_youtube || "";
        document.getElementById("profileWebsite").value = p.social_website || "";
        document.getElementById("profilePinterest").value = p.social_pinterest || "";
        document.getElementById("profileCustomLabel").value = p.social_custom_label || "";
        document.getElementById("profileCustomUrl").value = p.social_custom_url || "";
        if (p.avatar) {
          const img = document.getElementById("profileAvatarPreview");
          img.src = "../" + p.avatar;
          img.style.display = "block";
          document.getElementById("profileAvatarPlaceholder").style.display = "none";
        }
        if (p.card_image) {
          const img = document.getElementById("profileCardImagePreview");
          img.src = "../" + p.card_image;
          img.style.display = "block";
          document.getElementById("profileCardImagePlaceholder").style.display = "none";
        }
      } catch (err) {
        showToast(err.message || "خطا در بارگذاری پروفایل.", true);
      }
    }

    async function saveProfile() {
      const msgEl = document.getElementById("profileMsg");
      msgEl.textContent = "";
      msgEl.className = "form-msg";

      const fd = new FormData();
      fd.append("display_name", document.getElementById("profileDisplayName").value.trim());
      fd.append("job_title", document.getElementById("profileJobTitle").value.trim());
      fd.append("bio_short", document.getElementById("profileBioShort").value.trim());
      fd.append("bio_full", document.getElementById("profileBioFull").value.trim());
      fd.append("social_telegram", document.getElementById("profileTelegram").value.trim());
      fd.append("social_whatsapp", document.getElementById("profileWhatsapp").value.trim());
      fd.append("social_instagram", document.getElementById("profileInstagram").value.trim());
      fd.append("social_github", document.getElementById("profileGithub").value.trim());
      fd.append("social_email", document.getElementById("profileEmail").value.trim());
      fd.append("social_phone", document.getElementById("profilePhone").value.trim());
      fd.append("social_linkedin", document.getElementById("profileLinkedin").value.trim());
      fd.append("social_x", document.getElementById("profileX").value.trim());
      fd.append("social_youtube", document.getElementById("profileYoutube").value.trim());
      fd.append("social_website", document.getElementById("profileWebsite").value.trim());
      fd.append("social_pinterest", document.getElementById("profilePinterest").value.trim());
      fd.append("social_custom_label", document.getElementById("profileCustomLabel").value.trim());
      fd.append("social_custom_url", document.getElementById("profileCustomUrl").value.trim());
      if (profileAvatarFile) fd.append("avatar", profileAvatarFile);
      if (profileRemoveAvatarFlag) fd.append("remove_avatar", "1");
      if (profileCardImageFile) fd.append("card_image", profileCardImageFile);
      if (profileRemoveCardImageFlag) fd.append("remove_card_image", "1");
      fd.append("csrf", csrfToken);

      try {
        const res = await fetch("api/profile_save.php", {
          method: "POST",
          headers: { "X-CSRF-Token": csrfToken },
          body: fd,
        });
        const data = await res.json();
        if (!data.ok) throw new Error(data.message);
        showToast(data.message || "پروفایل ذخیره شد.");
        profileAvatarFile = null;
        profileRemoveAvatarFlag = false;
        profileCardImageFile = null;
        profileRemoveCardImageFlag = false;
      } catch (err) {
        msgEl.textContent = err.message || "خطا در ذخیره پروفایل.";
        msgEl.classList.add("is-error");
      }
    }

    /* ---------------------------------------------------------
       Settings: کارت‌های تیم ما (سوپر ادمین)
    --------------------------------------------------------- */
    let teamCardsCache = [];
    let tcCardImageFile = null;
    let tcRemoveCardImageFlag = false;

    const TEAM_SOCIAL_FIELD_IDS = {
      telegram: "tcTelegram", whatsapp: "tcWhatsapp", instagram: "tcInstagram", github: "tcGithub",
      email: "tcEmail", phone: "tcPhone", linkedin: "tcLinkedin", x: "tcX", youtube: "tcYoutube", website: "tcWebsite", pinterest: "tcPinterest",
    };

    function initTeamCardsSettings() {
      const listEl = document.getElementById("teamCardsList");
      if (!listEl) return;

      loadTeamCards();

      document.getElementById("addExtraMemberBtn")?.addEventListener("click", () => openTeamCardEditor("extra", null));
      document.getElementById("tcCancelBtn")?.addEventListener("click", closeTeamCardEditor);
      document.getElementById("tcSaveBtn")?.addEventListener("click", saveTeamCard);

      const drop = document.getElementById("tcCardImageDrop");
      const input = document.getElementById("tcCardImageInput");
      drop?.addEventListener("click", () => input.click());
      input?.addEventListener("change", () => {
        const file = input.files[0];
        if (!file) return;
        if (file.size > 6 * 1024 * 1024) {
          showToast("حجم عکس نباید بیشتر از ۶ مگابایت باشد.", true);
          return;
        }
        tcCardImageFile = file;
        tcRemoveCardImageFlag = false;
        const reader = new FileReader();
        reader.onload = (e) => {
          const img = document.getElementById("tcCardImagePreview");
          img.src = e.target.result;
          img.style.display = "block";
          document.getElementById("tcCardImagePlaceholder").style.display = "none";
        };
        reader.readAsDataURL(file);
      });
      document.getElementById("tcRemoveCardImageBtn")?.addEventListener("click", () => {
        tcCardImageFile = null;
        tcRemoveCardImageFlag = true;
        input.value = "";
        document.getElementById("tcCardImagePreview").style.display = "none";
        document.getElementById("tcCardImagePlaceholder").style.display = "block";
      });
    }

    async function loadTeamCards() {
      const listEl = document.getElementById("teamCardsList");
      try {
        const res = await fetch("api/team_list.php", { headers: { "X-CSRF-Token": csrfToken } });
        const data = await res.json();
        if (!data.ok) throw new Error(data.message || "خطا در دریافت لیست تیم.");
        teamCardsCache = data.data.items || [];
        renderTeamCardsList();
      } catch (err) {
        listEl.innerHTML = `<p class="empty-state">${escapeHtml(err.message || "خطا در دریافت لیست تیم.")}</p>`;
      }
    }

    function renderTeamCardsList() {
      const listEl = document.getElementById("teamCardsList");
      if (!teamCardsCache.length) {
        listEl.innerHTML = '<p class="empty-state">هنوز هیچ کارتی ثبت نشده است.</p>';
        return;
      }
      listEl.innerHTML = teamCardsCache
        .map((item, i) => {
          const img = item.card_image || item.avatar;
          const thumb = img
            ? `<img src="../${img}" alt="">`
            : `<span>${escapeHtml((item.name || "؟").charAt(0))}</span>`;
          return `
          <div class="team-card-row${item.is_enabled ? "" : " is-disabled"}" data-type="${item.type}" data-id="${item.id}">
            <span class="team-card-row-thumb">${thumb}</span>
            <span class="team-card-row-info">
              <strong>${escapeHtml(item.name || "")}</strong>
              <small>${item.type === "admin" ? "دارای حساب ادمین" : "بدون حساب ادمین"}${item.job_title ? " · " + escapeHtml(item.job_title) : ""}${item.is_enabled ? "" : " · غیرفعال"}</small>
            </span>
            <span class="team-card-row-actions">
              <button type="button" class="btn-ghost tc-move-up" ${i === 0 ? "disabled" : ""} title="جابه‌جایی به بالا">▲</button>
              <button type="button" class="btn-ghost tc-move-down" ${i === teamCardsCache.length - 1 ? "disabled" : ""} title="جابه‌جایی به پایین">▼</button>
              <button type="button" class="btn-ghost tc-edit">ویرایش</button>
              ${item.type === "extra" ? '<button type="button" class="table-del-btn tc-delete">حذف</button>' : ""}
            </span>
          </div>`;
        })
        .join("");

      listEl.querySelectorAll(".team-card-row").forEach((row) => {
        const type = row.dataset.type;
        const id = parseInt(row.dataset.id, 10);
        row.querySelector(".tc-edit")?.addEventListener("click", () => {
          const item = teamCardsCache.find((x) => x.type === type && x.id === id);
          if (item) openTeamCardEditor(type, item);
        });
        row.querySelector(".tc-delete")?.addEventListener("click", () => deleteExtraMember(id));
        row.querySelector(".tc-move-up")?.addEventListener("click", () => moveTeamCard(type, id, -1));
        row.querySelector(".tc-move-down")?.addEventListener("click", () => moveTeamCard(type, id, 1));
      });
    }

    function openTeamCardEditor(type, item) {
      document.getElementById("teamCardEditorBlock").style.display = "block";
      document.getElementById("teamCardEditorTitle").textContent = type === "admin" ? "ویرایش کارت ادمین" : (item ? "ویرایش عضو تیم" : "افزودن عضو تیم");
      document.getElementById("tcType").value = type;
      document.getElementById("tcId").value = item ? item.id : "";

      const nameInput = document.getElementById("tcName");
      const nameLabel = document.getElementById("tcNameLabel");
      if (type === "admin") {
        nameInput.value = item ? item.name : "";
        nameInput.disabled = true;
        nameLabel.textContent = "نام (از پروفایل خودِ ادمین)";
      } else {
        nameInput.value = item ? item.name : "";
        nameInput.disabled = false;
        nameLabel.textContent = "نام";
      }

      document.getElementById("tcJobTitle").value = item?.job_title || "";
      document.getElementById("tcBioShort").value = item?.bio_short || "";
      document.getElementById("tcEnabled").checked = item ? !!item.is_enabled : true;

      Object.entries(TEAM_SOCIAL_FIELD_IDS).forEach(([key, elId]) => {
        const social = (item?.socials || []).find((s) => s.key === key);
        document.getElementById(elId).value = social ? social.value : "";
      });
      const customSocial = (item?.socials || []).find((s) => s.key === "custom");
      document.getElementById("tcCustomLabel").value = customSocial ? customSocial.label : "";
      document.getElementById("tcCustomUrl").value = customSocial ? customSocial.value : "";

      tcCardImageFile = null;
      tcRemoveCardImageFlag = false;
      const img = document.getElementById("tcCardImagePreview");
      const placeholder = document.getElementById("tcCardImagePlaceholder");
      document.getElementById("tcCardImageInput").value = "";
      if (item?.card_image) {
        img.src = "../" + item.card_image;
        img.style.display = "block";
        placeholder.style.display = "none";
      } else {
        img.style.display = "none";
        placeholder.style.display = "block";
      }

      document.getElementById("teamCardEditorMsg").textContent = "";
      document.getElementById("teamCardEditorBlock").scrollIntoView({ behavior: "smooth", block: "start" });
    }

    function closeTeamCardEditor() {
      document.getElementById("teamCardEditorBlock").style.display = "none";
    }

    async function saveTeamCard() {
      const msgEl = document.getElementById("teamCardEditorMsg");
      msgEl.textContent = "";
      msgEl.className = "form-msg";

      const type = document.getElementById("tcType").value;
      const id = document.getElementById("tcId").value;

      const fd = new FormData();
      fd.append("type", type);
      if (id) fd.append("id", id);
      if (type === "extra") fd.append("name", document.getElementById("tcName").value.trim());
      fd.append("job_title", document.getElementById("tcJobTitle").value.trim());
      fd.append("bio_short", document.getElementById("tcBioShort").value.trim());
      fd.append("is_enabled", document.getElementById("tcEnabled").checked ? "1" : "0");
      Object.entries(TEAM_SOCIAL_FIELD_IDS).forEach(([key, elId]) => {
        fd.append("social_" + key, document.getElementById(elId).value.trim());
      });
      fd.append("social_custom_label", document.getElementById("tcCustomLabel").value.trim());
      fd.append("social_custom_url", document.getElementById("tcCustomUrl").value.trim());
      if (tcCardImageFile) fd.append("card_image", tcCardImageFile);
      if (tcRemoveCardImageFlag) fd.append("remove_card_image", "1");
      fd.append("csrf", csrfToken);

      try {
        const res = await fetch("api/team_save.php", { method: "POST", headers: { "X-CSRF-Token": csrfToken }, body: fd });
        const data = await res.json();
        if (!data.ok) throw new Error(data.message);
        showToast(data.message || "ذخیره شد.");
        closeTeamCardEditor();
        loadTeamCards();
      } catch (err) {
        msgEl.textContent = err.message || "خطا در ذخیره کارت.";
        msgEl.classList.add("is-error");
      }
    }

    async function deleteExtraMember(id) {
      if (!confirm("این عضو تیم حذف شود؟")) return;
      try {
        const data = await apiCall("api/team_delete.php", { id });
        showToast(data.message || "حذف شد.");
        loadTeamCards();
      } catch (err) {
        showToast(err.message || "خطا در حذف.", true);
      }
    }

    async function moveTeamCard(type, id, direction) {
      const idx = teamCardsCache.findIndex((x) => x.type === type && x.id === id);
      if (idx === -1) return;
      const swapWith = idx + direction;
      if (swapWith < 0 || swapWith >= teamCardsCache.length) return;
      [teamCardsCache[idx], teamCardsCache[swapWith]] = [teamCardsCache[swapWith], teamCardsCache[idx]];
      renderTeamCardsList();
      try {
        await apiCall("api/team_reorder.php", {
          items: teamCardsCache.map((x) => ({ type: x.type, id: x.id })),
        });
      } catch (err) {
        showToast(err.message || "خطا در ذخیره ترتیب.", true);
        loadTeamCards();
      }
    }

    /* ---------------------------------------------------------
       کشیدن و رها کردن عمومی (Drag & Drop) برای فهرست‌های قابل مرتب‌سازی
    --------------------------------------------------------- */
    function attachRowDragReorder(listEl, onReordered) {
      let dragEl = null;

      listEl.addEventListener("dragstart", (e) => {
        const row = e.target.closest(".team-card-row[draggable='true']");
        if (!row) return;
        dragEl = row;
        row.classList.add("is-dragging");
        e.dataTransfer.effectAllowed = "move";
        try { e.dataTransfer.setData("text/plain", row.dataset.id || ""); } catch (err) {}
      });

      listEl.addEventListener("dragover", (e) => {
        if (!dragEl) return;
        e.preventDefault();
        const row = e.target.closest(".team-card-row[draggable='true']");
        if (!row || row === dragEl) return;
        listEl.querySelectorAll(".team-card-row.is-drag-over").forEach((r) => r.classList.remove("is-drag-over"));
        row.classList.add("is-drag-over");
        const rect = row.getBoundingClientRect();
        const before = (e.clientY - rect.top) < rect.height / 2;
        row.parentNode.insertBefore(dragEl, before ? row : row.nextSibling);
      });

      listEl.addEventListener("dragend", () => {
        listEl.querySelectorAll(".is-drag-over").forEach((r) => r.classList.remove("is-drag-over"));
        if (dragEl) dragEl.classList.remove("is-dragging");
        dragEl = null;
        const ids = Array.from(listEl.querySelectorAll(".team-card-row[draggable='true']")).map((r) => r.dataset.id);
        onReordered(ids);
      });
    }

    /* ---------------------------------------------------------
       Settings: دستاوردی چشم‌نواز / جوایز (سوپر ادمین)
    --------------------------------------------------------- */
    let awardsCache = [];
    let awardImageFile = null;

    function initAwardsSettings() {
      const listEl = document.getElementById("awardsList");
      if (!listEl) return;

      loadAwards();

      const drop = document.getElementById("awardImageDrop");
      const input = document.getElementById("awardImageInput");
      drop?.addEventListener("click", () => input.click());
      input?.addEventListener("change", () => {
        const file = input.files[0];
        if (!file) return;
        if (file.size > 6 * 1024 * 1024) {
          showToast("حجم عکس نباید بیشتر از ۶ مگابایت باشد.", true);
          return;
        }
        awardImageFile = file;
        const reader = new FileReader();
        reader.onload = (e) => {
          const img = document.getElementById("awardImagePreview");
          img.src = e.target.result;
          img.style.display = "block";
          document.getElementById("awardImagePlaceholder").style.display = "none";
        };
        reader.readAsDataURL(file);
      });

      document.getElementById("awardAddBtn")?.addEventListener("click", addAward);

      attachRowDragReorder(listEl, async (ids) => {
        try {
          const data = await apiCall("api/awards_reorder.php", { items: ids.map((id) => parseInt(id, 10)) });
          if (!data.ok) throw new Error(data.message);
        } catch (err) {
          showToast(err.message || "خطا در ذخیره ترتیب.", true);
          loadAwards();
        }
      });
    }

    async function loadAwards() {
      const listEl = document.getElementById("awardsList");
      try {
        const res = await fetch("api/awards_list.php", { headers: { "X-CSRF-Token": csrfToken } });
        const data = await res.json();
        if (!data.ok) throw new Error(data.message || "خطا در دریافت لیست جوایز.");
        awardsCache = data.data.awards || [];
        renderAwardsList();
      } catch (err) {
        listEl.innerHTML = `<p class="empty-state">${escapeHtml(err.message || "خطا در دریافت لیست جوایز.")}</p>`;
      }
    }

    function renderAwardsList() {
      const listEl = document.getElementById("awardsList");
      if (!awardsCache.length) {
        listEl.innerHTML = '<p class="empty-state">هنوز هیچ جایزه‌ای ثبت نشده است.</p>';
        return;
      }
      listEl.innerHTML = awardsCache
        .map(
          (item) => `
          <div class="team-card-row" draggable="true" data-id="${item.id}">
            <span class="tcr-drag-handle" title="جابه‌جایی">⠿</span>
            <span class="team-card-row-thumb team-card-row-thumb--square"><img src="../${item.image}" alt=""></span>
            <span class="team-card-row-info">
              <strong>${escapeHtml(item.title || "بدون عنوان")}</strong>
            </span>
            <span class="team-card-row-actions">
              <button type="button" class="table-del-btn award-delete">حذف</button>
            </span>
          </div>`
        )
        .join("");

      listEl.querySelectorAll(".team-card-row").forEach((row) => {
        const id = parseInt(row.dataset.id, 10);
        row.querySelector(".award-delete")?.addEventListener("click", () => deleteAward(id));
      });
    }

    async function addAward() {
      const msgEl = document.getElementById("awardAddMsg");
      msgEl.textContent = "";
      msgEl.className = "form-msg";

      if (!awardImageFile) {
        msgEl.textContent = "لطفاً یک عکس انتخاب کنید.";
        msgEl.classList.add("is-error");
        return;
      }

      const fd = new FormData();
      fd.append("title", document.getElementById("awardTitle").value.trim());
      fd.append("sort_order", String((awardsCache.length + 1) * 10));
      fd.append("image", awardImageFile);
      fd.append("csrf", csrfToken);

      try {
        const res = await fetch("api/awards_save.php", { method: "POST", headers: { "X-CSRF-Token": csrfToken }, body: fd });
        const data = await res.json();
        if (!data.ok) throw new Error(data.message);
        showToast(data.message || "جایزه اضافه شد.");
        awardImageFile = null;
        document.getElementById("awardImageInput").value = "";
        document.getElementById("awardImagePreview").style.display = "none";
        document.getElementById("awardImagePlaceholder").style.display = "block";
        document.getElementById("awardTitle").value = "";
        loadAwards();
      } catch (err) {
        msgEl.textContent = err.message || "خطا در افزودن جایزه.";
        msgEl.classList.add("is-error");
      }
    }

    async function deleteAward(id) {
      if (!confirm("این جایزه حذف شود؟")) return;
      try {
        const data = await apiCall("api/awards_delete.php", { id });
        if (!data.ok) throw new Error(data.message);
        showToast(data.message || "حذف شد.");
        loadAwards();
      } catch (err) {
        showToast(err.message || "خطا در حذف.", true);
      }
    }

    /* ---------------------------------------------------------
       Settings: افراد توسعه دهنده (سوپر ادمین)
    --------------------------------------------------------- */
    let devTeamCache = [];
    let devTeamSaveTimer = null;

    function initDevTeamSettings() {
      const listEl = document.getElementById("devTeamAdminsList");
      if (!listEl) return;

      loadDevTeamAdmins();

      attachRowDragReorder(listEl, () => saveDevTeamOrder());
    }

    async function loadDevTeamAdmins() {
      const listEl = document.getElementById("devTeamAdminsList");
      try {
        const res = await fetch("api/dev_team_list.php", { headers: { "X-CSRF-Token": csrfToken } });
        const data = await res.json();
        if (!data.ok) throw new Error(data.message || "خطا در دریافت لیست ادمین‌ها.");
        devTeamCache = data.data.admins || [];
        renderDevTeamAdminsList();
      } catch (err) {
        listEl.innerHTML = `<p class="empty-state">${escapeHtml(err.message || "خطا در دریافت لیست ادمین‌ها.")}</p>`;
      }
    }

    function renderDevTeamAdminsList() {
      const listEl = document.getElementById("devTeamAdminsList");
      if (!devTeamCache.length) {
        listEl.innerHTML = '<p class="empty-state">هیچ ادمینی یافت نشد.</p>';
        return;
      }
      listEl.innerHTML = devTeamCache
        .map((item) => {
          const img = item.card_image || item.avatar;
          const thumb = img
            ? `<img src="../${img}" alt="">`
            : `<span>${escapeHtml((item.display_name || "؟").charAt(0))}</span>`;
          return `
          <div class="team-card-row${item.dev_team_enabled ? "" : " is-disabled"}" draggable="true" data-id="${item.id}">
            <span class="tcr-drag-handle" title="جابه‌جایی">⠿</span>
            <span class="team-card-row-thumb">${thumb}</span>
            <span class="team-card-row-info">
              <strong>${escapeHtml(item.display_name || "")}</strong>
              <small>${item.job_title ? escapeHtml(item.job_title) : "بدون عنوان شغلی"}</small>
            </span>
            <label class="team-card-row-toggle">
              <input type="checkbox" class="dev-team-toggle" ${item.dev_team_enabled ? "checked" : ""}>
              نمایش
            </label>
          </div>`;
        })
        .join("");

      listEl.querySelectorAll(".team-card-row").forEach((row) => {
        const id = parseInt(row.dataset.id, 10);
        row.querySelector(".dev-team-toggle")?.addEventListener("change", (e) => {
          const item = devTeamCache.find((x) => x.id === id);
          if (item) item.dev_team_enabled = e.target.checked ? 1 : 0;
          row.classList.toggle("is-disabled", !e.target.checked);
          saveDevTeamOrder();
        });
      });
    }

    function saveDevTeamOrder() {
      const listEl = document.getElementById("devTeamAdminsList");
      const rows = Array.from(listEl.querySelectorAll(".team-card-row[draggable='true']"));
      const items = rows.map((row, i) => {
        const id = parseInt(row.dataset.id, 10);
        const item = devTeamCache.find((x) => x.id === id);
        return { id, enabled: item ? !!item.dev_team_enabled : row.querySelector(".dev-team-toggle")?.checked, sort_order: (i + 1) * 10 };
      });

      clearTimeout(devTeamSaveTimer);
      devTeamSaveTimer = setTimeout(async () => {
        try {
          const data = await apiCall("api/dev_team_save.php", { items });
          if (!data.ok) throw new Error(data.message);
          showToast("تنظیمات ذخیره شد.");
        } catch (err) {
          showToast(err.message || "خطا در ذخیره تنظیمات.", true);
          loadDevTeamAdmins();
        }
      }, 250);
    }
  })();