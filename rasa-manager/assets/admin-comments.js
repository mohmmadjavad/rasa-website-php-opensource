/* =========================================================
   admin-comments.js — تب «نظرات کاربران» در پنل ادمین
   ========================================================= */

(function () {
  "use strict";

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || "";

  let lastSeenCommentId = parseInt(localStorage.getItem("resa_last_comment_id") || "0", 10) || 0;
  let hasCheckedNotifications = false;

  document.addEventListener("DOMContentLoaded", () => {
    const steps = [
      ["loadComments", () => loadComments()],
      ["initCommentsFilters", initCommentsFilters],
      ["initCommentReplyModal", initCommentReplyModal],
      ["initCommentsAutoApproveSetting", initCommentsAutoApproveSetting],
      ["initCommentsPolling", initCommentsPolling],
    ];
    steps.forEach(([name, fn]) => {
      try {
        fn();
      } catch (err) {
        console.error("[admin-comments] " + name + " failed:", err);
      }
    });
  });

  /**
   * هر ۴۵ ثانیه یک‌بار، بی‌سروصدا فهرست نظرات را دوباره می‌گیرد؛ اگر نظر
   * تازه‌ای (با شناسه بزرگ‌تر از آخرین نظر دیده‌شده) پیدا شود، یک اعلان
   * toast نشان می‌دهد. آخرین شناسه دیده‌شده در localStorage نگه داشته
   * می‌شود تا با رفرش صفحه هم اعلان تکراری نشان داده نشود.
   */
  function initCommentsPolling() {
    setInterval(() => {
      loadComments(true).catch(() => {});
    }, 45000);
  }

  /* ---------------------------------------------------------
     Helpers (self-contained, mirror admin.js helpers)
  --------------------------------------------------------- */
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

  function showToast(message, isError) {
    const toast = document.getElementById("toast");
    if (!toast) return;
    toast.textContent = message;
    toast.classList.toggle("is-error", !!isError);
    toast.classList.add("is-visible");
    clearTimeout(showToast._t);
    showToast._t = setTimeout(() => toast.classList.remove("is-visible"), 3200);
  }

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
     List + filters
  --------------------------------------------------------- */
  let commentsCache = [];
  let searchDebounce = null;
  let currentCommentsPage = 1;

  function initCommentsFilters() {
    const searchInput = document.getElementById("commentSearchInput");
    const statusFilter = document.getElementById("commentStatusFilter");
    const refreshBtn = document.getElementById("refreshCommentsBtn");
    if (!searchInput && !statusFilter) return;

    const resetAndLoad = () => {
      currentCommentsPage = 1;
      loadComments();
    };
    searchInput?.addEventListener("input", () => {
      clearTimeout(searchDebounce);
      searchDebounce = setTimeout(resetAndLoad, 350);
    });
    statusFilter?.addEventListener("change", resetAndLoad);
    refreshBtn?.addEventListener("click", resetAndLoad);
  }

  async function loadComments(silent, page) {
    if (page) currentCommentsPage = page;
    const listEl = document.getElementById("commentsList");
    if (!listEl) return;
    const q = document.getElementById("commentSearchInput")?.value.trim() || "";
    const status = document.getElementById("commentStatusFilter")?.value || "";

    const params = new URLSearchParams();
    if (q) params.set("q", q);
    if (status) params.set("status", status);
    // در حالت silent (پولینگ پس‌زمینه برای اعلان نظر تازه) همیشه صفحه اول را چک می‌کنیم،
    // چون تازه‌ترین نظرات همیشه آنجا هستند؛ صفحه‌ی فعلیِ کاربر دست‌نخورده می‌ماند.
    params.set("page", String(silent ? 1 : currentCommentsPage));

    try {
      const data = await apiCall("api/comments_list.php?" + params.toString());
      if (!data.ok) throw new Error(data.message || "خطا در دریافت نظرات.");
      commentsCache = data.data.comments;
      if (!silent) {
        renderComments(commentsCache);
        renderCommentsPagination(data.data);
      }
      updateCommentsStats(data.data);
      checkForNewComments(commentsCache);
    } catch (err) {
      if (!silent) listEl.innerHTML = '<div class="empty-state">خطا در بارگذاری نظرات. صفحه را رفرش کنید.</div>';
    }
  }

  function renderCommentsPagination(data) {
    const el = document.getElementById("commentsPagination");
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
      btn.addEventListener("click", () => loadComments(false, parseInt(btn.dataset.page, 10)));
    });
  }

  /**
   * مقایسه‌ی بزرگ‌ترین شناسه‌ی نظرِ دیده‌شده با دفعه‌ی قبل؛ اگر نظر(های)
   * تازه‌ای رسیده باشد، toast اعلان نشان می‌دهد.
   */
  function checkForNewComments(list) {
    if (!list.length) return;
    const maxId = list.reduce((m, c) => Math.max(m, c.id), 0);
    if (hasCheckedNotifications && lastSeenCommentId && maxId > lastSeenCommentId) {
      const newCount = list.filter((c) => c.id > lastSeenCommentId).length;
      showToast(newCount === 1 ? "یک نظر جدید دریافت شد." : `${newCount} نظر جدید دریافت شد.`);
    }
    if (maxId > lastSeenCommentId) {
      lastSeenCommentId = maxId;
      localStorage.setItem("resa_last_comment_id", String(maxId));
    }
    hasCheckedNotifications = true;
  }

  function updateCommentsStats(data) {
    const totalEl = document.getElementById("cmTotalCount");
    const pendingEl = document.getElementById("cmPendingCount");
    const approvedEl = document.getElementById("cmApprovedCount");
    if (totalEl) totalEl.textContent = (data.total_count || 0).toLocaleString("fa-IR");
    if (pendingEl) pendingEl.textContent = (data.pending_count || 0).toLocaleString("fa-IR");
    if (approvedEl) approvedEl.textContent = (data.approved_count || 0).toLocaleString("fa-IR");

    const badge = document.getElementById("sidebarCommentsBadge");
    if (badge) {
      const pending = data.pending_count || 0;
      badge.textContent = pending;
      badge.style.display = pending > 0 ? "flex" : "none";
    }
  }

  const STATUS_LABELS = { pending: "در انتظار تایید", approved: "تاییدشده", rejected: "رد شده" };

  function statusBadgeHtml(status) {
    const cls = status === "approved" ? "status-badge--published" : status === "rejected" ? "" : "status-badge--draft";
    const extra = status === "rejected" ? "background:var(--danger-bg); color:var(--danger);" : "";
    return `<span class="status-badge ${cls}" style="${extra}">${STATUS_LABELS[status] || status}</span>`;
  }

  function renderComments(list) {
    const listEl = document.getElementById("commentsList");
    if (!list.length) {
      listEl.innerHTML = '<div class="empty-state">نظری یافت نشد.</div>';
      return;
    }
    listEl.innerHTML = list.map(commentCardHtml).join("");

    listEl.querySelectorAll("[data-action='approve']").forEach((btn) => {
      btn.addEventListener("click", () => setCommentStatus(parseInt(btn.dataset.id, 10), "approved"));
    });
    listEl.querySelectorAll("[data-action='reject']").forEach((btn) => {
      btn.addEventListener("click", () => setCommentStatus(parseInt(btn.dataset.id, 10), "rejected"));
    });
    listEl.querySelectorAll("[data-action='pending']").forEach((btn) => {
      btn.addEventListener("click", () => setCommentStatus(parseInt(btn.dataset.id, 10), "pending"));
    });
    listEl.querySelectorAll("[data-action='delete']").forEach((btn) => {
      btn.addEventListener("click", () => deleteComment(parseInt(btn.dataset.id, 10)));
    });
    listEl.querySelectorAll("[data-action='reply']").forEach((btn) => {
      btn.addEventListener("click", () => openCommentReplyModal(parseInt(btn.dataset.id, 10)));
    });
  }

  function commentCardHtml(c) {
    const authorLabel = c.author_type === "admin"
      ? `<span class="cm-author cm-author--admin">${escapeHtml(c.author_name || "ادمین")} <span class="role-badge role-badge--comment_admin">پاسخ ادمین</span></span>`
      : `<span class="cm-author">${escapeHtml(c.author_name || "کاربر")}</span>`;

    const replyContext = c.parent_id
      ? `<div class="cm-reply-context">در پاسخ به ${escapeHtml(c.parent_author_name || "نظر دیگر")}</div>`
      : "";

    const spamNote = c.spam_reason
      ? `<div class="cm-spam-note">⚠️ مشکوک به اسپم — ${escapeHtml(c.spam_reason)}</div>`
      : "";

    const actions = c.can_manage
      ? `
        <button class="btn-ghost btn-ghost--sm" data-action="reply" data-id="${c.id}">پاسخ</button>
        ${c.status !== "approved" ? `<button class="btn-ghost btn-ghost--sm" data-action="approve" data-id="${c.id}">تایید</button>` : ""}
        ${c.status !== "rejected" ? `<button class="btn-ghost btn-ghost--sm" data-action="reject" data-id="${c.id}">رد</button>` : ""}
        ${c.status !== "pending" ? `<button class="btn-ghost btn-ghost--sm" data-action="pending" data-id="${c.id}">در انتظار</button>` : ""}
        <button class="table-del-btn" data-action="delete" data-id="${c.id}">حذف</button>`
      : '<span class="perm-locked-note">خارج از دسترسی شما</span>';

    return `
      <div class="comment-card ${c.spam_reason ? "comment-card--spam" : ""}" data-id="${c.id}">
        <div class="comment-card-head">
          ${authorLabel}
          ${statusBadgeHtml(c.status)}
          <span class="comment-card-date">${formatDate(c.created_at)}</span>
        </div>
        ${replyContext}
        ${spamNote}
        <p class="comment-card-text">${escapeHtml(c.content)}</p>
        <div class="comment-card-meta">
          <a href="../article.html?slug=${encodeURIComponent(c.article_slug)}" target="_blank" rel="noopener">${escapeHtml(c.article_title)}</a>
        </div>
        <div class="comment-card-actions">${actions}</div>
      </div>`;
  }

  async function setCommentStatus(id, status) {
    try {
      const data = await apiCall("api/comments_approve.php", { id, status });
      if (!data.ok) throw new Error(data.message);
      showToast("وضعیت نظر بروزرسانی شد.");
      await loadComments();
    } catch (err) {
      showToast(err.message || "خطا در بروزرسانی وضعیت نظر.", true);
    }
  }

  async function deleteComment(id) {
    if (!confirm("آیا از حذف این نظر (و پاسخ‌های آن) مطمئن هستید؟")) return;
    try {
      const data = await apiCall("api/comments_delete.php", { id });
      if (!data.ok) throw new Error(data.message);
      showToast("نظر حذف شد.");
      await loadComments();
    } catch (err) {
      showToast(err.message || "خطا در حذف نظر.", true);
    }
  }

  /* ---------------------------------------------------------
     Reply modal
  --------------------------------------------------------- */
  function initCommentReplyModal() {
    const modal = document.getElementById("commentReplyModal");
    const closeBtn = document.getElementById("commentReplyCloseBtn");
    const form = document.getElementById("commentReplyForm");
    if (!modal || !form) return;

    closeBtn?.addEventListener("click", () => modal.classList.remove("is-open"));
    modal.addEventListener("click", (e) => {
      if (e.target === modal) modal.classList.remove("is-open");
    });

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const msgEl = document.getElementById("commentReplyMsg");
      msgEl.textContent = "";
      msgEl.className = "form-msg";
      const content = document.getElementById("commentReplyText").value.trim();
      const articleId = parseInt(form.dataset.articleId, 10);
      const parentId = parseInt(form.dataset.parentId, 10);
      if (!content) return;

      try {
        const data = await apiCall("api/comments_reply.php", { article_id: articleId, parent_id: parentId, content });
        if (!data.ok) throw new Error(data.message);
        showToast("پاسخ شما ثبت شد.");
        modal.classList.remove("is-open");
        form.reset();
        await loadComments();
      } catch (err) {
        msgEl.textContent = err.message || "خطا در ارسال پاسخ.";
        msgEl.classList.add("is-error");
      }
    });
  }

  function openCommentReplyModal(id) {
    const c = commentsCache.find((x) => x.id === id);
    if (!c) return;
    const modal = document.getElementById("commentReplyModal");
    const form = document.getElementById("commentReplyForm");
    if (!modal || !form) return;

    form.dataset.articleId = c.article_id;
    form.dataset.parentId = c.id;
    document.getElementById("commentReplyTarget").textContent = `پاسخ به ${c.author_name || "کاربر"} — ${c.article_title}`;
    document.getElementById("commentReplyOriginalText").textContent = c.content;
    document.getElementById("commentReplyText").value = "";
    document.getElementById("commentReplyMsg").textContent = "";
    modal.classList.add("is-open");
  }

  /* ---------------------------------------------------------
     Super-admin setting: auto-approve comments
  --------------------------------------------------------- */
  function initCommentsAutoApproveSetting() {
    const toggle = document.getElementById("commentsAutoApproveToggle");
    if (!toggle) return;

    apiCall("api/settings_get.php")
      .then((data) => {
        if (data.ok) toggle.checked = !!data.data.comments_auto_approve;
      })
      .catch(() => {});

    toggle.addEventListener("change", async () => {
      try {
        const data = await apiCall("api/settings_save.php", { comments_auto_approve: toggle.checked });
        if (!data.ok) throw new Error(data.message);
        showToast(toggle.checked ? "نظرات از این پس به‌صورت خودکار تایید می‌شوند." : "نظرات از این پس نیاز به تایید ادمین دارند.");
      } catch (err) {
        toggle.checked = !toggle.checked;
        showToast(err.message || "خطا در ذخیره تنظیمات.", true);
      }
    });
  }
})();
