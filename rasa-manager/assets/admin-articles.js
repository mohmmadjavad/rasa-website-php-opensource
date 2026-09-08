/* =========================================================
   admin-articles.js — مدیریت وبلاگ‌ها پنل ادمین رسا
   ========================================================= */
(function () {
  "use strict";

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || "";
  const currentAdminRole = document.querySelector('meta[name="admin-role"]')?.content || "admin";
  const currentAdminId = parseInt(document.querySelector('meta[name="admin-id"]')?.content || "0", 10);
  const isSuperAdmin = currentAdminRole === "super_admin";
  const blogScope = document.querySelector('meta[name="blog-scope"]')?.content || "own";
  const canSeeAllArticles = isSuperAdmin || blogScope === "all";

  let categoriesCache = [];   // همه دسته‌بندی‌ها (اصلی + زیر)
  let adminsCache = [];       // برای انتخاب نویسنده
  let articlesCache = [];
  let currentArticlesPage = 1;
  let currentTags = [];
  let selectedSubcatIds = new Set();
  let currentEditId = null;
  let currentCoverFile = null;
  let removeCoverFlag = false;
  let existingCoverPath = null;
  let slugManuallyEdited = false;
  let isHtmlSourceMode = false;
  let catIconFile = null;
  let catPosterFile = null;

  document.addEventListener("DOMContentLoaded", () => {
    if (!document.getElementById("tab-articles")) return;

    const steps = [
      ["wireFilters", wireFilters],
      ["wireEditorShell", wireEditorShell],
      ["wireCategoryModal", wireCategoryModal],
      ["wireToolbar", wireToolbar],
      ["wireCoverUpload", wireCoverUpload],
      ["wireTagsInput", wireTagsInput],
      ["wireSeoCounters", wireSeoCounters],
      ["wireSlugTracking", wireSlugTracking],
      ["loadAdminsForAuthorFields", loadAdminsForAuthorFields],
      ["loadCategories", loadCategories],
      ["loadArticles", loadArticles],
    ];
    steps.forEach(([name, fn]) => {
      try {
        fn();
      } catch (err) {
        console.error("[admin-articles] " + name + " failed:", err);
      }
    });
  });

  /* ---------------------------------------------------------
     Helpers
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
  function initToast() {}

  function escapeHtml(str) {
    const div = document.createElement("div");
    div.textContent = str ?? "";
    return div.innerHTML;
  }

  function formatDate(isoLike) {
    if (!isoLike) return "—";
    try {
      const d = new Date(isoLike.replace(" ", "T"));
      return d.toLocaleString("fa-IR", { dateStyle: "medium", timeStyle: "short" });
    } catch (e) {
      return isoLike;
    }
  }

  async function apiGet(url) {
    const res = await fetch(url, { headers: { "X-CSRF-Token": csrfToken } });
    return res.json();
  }

  async function apiPostJson(url, body) {
    const res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json", "X-CSRF-Token": csrfToken },
      body: JSON.stringify(body || {}),
    });
    return res.json();
  }

  async function apiPostForm(url, formData) {
    formData.append("csrf", csrfToken);
    const res = await fetch(url, {
      method: "POST",
      headers: { "X-CSRF-Token": csrfToken },
      body: formData,
    });
    return res.json();
  }

  /* ---------------------------------------------------------
     Categories: load + render into all consumers
  --------------------------------------------------------- */
  async function loadCategories() {
    try {
      const data = await apiGet("api/categories_list.php");
      if (!data.ok) throw new Error(data.message);
      categoriesCache = data.data.categories;
      renderCategoryTree();
      renderCategorySelects();
    } catch (err) {
      const tree = document.getElementById("categoryTree");
      if (tree) tree.innerHTML = '<div class="empty-state">خطا در بارگذاری دسته‌بندی‌ها.</div>';
    }
  }

  /* ---------------------------------------------------------
     Authors (admins) — for author select + filter
  --------------------------------------------------------- */
  async function loadAdminsForAuthorFields() {
    try {
      const data = await apiGet("api/admins_list.php");
      if (!data.ok) throw new Error(data.message);
      adminsCache = data.data.admins;
    } catch (err) {
      adminsCache = [];
    }
    renderAuthorFields();
  }

  function renderAuthorFields() {
    const fieldAuthor = document.getElementById("fieldAuthor");
    if (fieldAuthor) {
      const opts = adminsCache.map((a) => `<option value="${a.id}">${escapeHtml(a.display_name || a.username)}</option>`).join("");
      fieldAuthor.innerHTML = opts;
      if (!canSeeAllArticles) {
        fieldAuthor.value = String(currentAdminId);
        fieldAuthor.disabled = true;
      }
    }

    const filterSel = document.getElementById("articleAuthorFilter");
    if (filterSel) {
      if (canSeeAllArticles && adminsCache.length > 1) {
        const opts = adminsCache.map((a) => `<option value="${a.id}">${escapeHtml(a.display_name || a.username)}</option>`).join("");
        filterSel.innerHTML = '<option value="">همه نویسنده‌ها</option>' + opts;
        filterSel.style.display = "";
      } else {
        filterSel.style.display = "none";
      }
    }
  }

  function mainCategories() {
    return categoriesCache.filter((c) => c.parent_id === null);
  }
  function subcategoriesOf(parentId) {
    return categoriesCache.filter((c) => c.parent_id === parentId);
  }

  function renderCategoryTree() {
    const tree = document.getElementById("categoryTree");
    if (!tree) return;
    const mains = mainCategories();
    if (!mains.length) {
      tree.innerHTML = '<div class="empty-state">هنوز دسته‌بندی‌ای ساخته نشده است.</div>';
      return;
    }
    const actionsHtml = (cat) =>
      isSuperAdmin
        ? `<div class="cat-row-actions">
             <button class="cat-row-edit" data-id="${cat.id}" title="ویرایش">✎</button>
             <button class="cat-row-del" data-id="${cat.id}" title="حذف">&times;</button>
           </div>`
        : "";
    let html = "";
    mains.forEach((m) => {
      const iconHtml = m.icon_image ? `<img class="cat-row-icon" src="../${m.icon_image}" alt="">` : "📁";
      const descHtml = m.description ? `<div class="cat-row-desc">${escapeHtml(m.description)}</div>` : "";
      html += `
        <div class="cat-row" data-id="${m.id}">
          <div class="cat-row-main">
            <span class="cat-row-name">${iconHtml} ${escapeHtml(m.name)} <span class="cat-row-count">(${m.article_count} وبلاگ)</span></span>
            ${descHtml}
          </div>
          ${actionsHtml(m)}
        </div>`;
      subcategoriesOf(m.id).forEach((s) => {
        html += `
          <div class="cat-row is-sub" data-id="${s.id}">
            <span class="cat-row-name">└ ${escapeHtml(s.name)} <span class="cat-row-count">(${s.article_count} وبلاگ)</span></span>
            ${actionsHtml(s)}
          </div>`;
      });
    });
    tree.innerHTML = html;
    tree.querySelectorAll(".cat-row-del").forEach((btn) => {
      btn.addEventListener("click", () => deleteCategory(parseInt(btn.dataset.id, 10)));
    });
    tree.querySelectorAll(".cat-row-edit").forEach((btn) => {
      btn.addEventListener("click", () => {
        const cat = categoriesCache.find((c) => c.id === parseInt(btn.dataset.id, 10));
        if (cat) startEditCategory(cat);
      });
    });
  }

  function renderCategorySelects() {
    const mains = mainCategories();
    const opts = mains.map((m) => `<option value="${m.id}">${escapeHtml(m.name)}</option>`).join("");

    const filterSel = document.getElementById("articleCategoryFilter");
    if (filterSel) {
      const current = filterSel.value;
      filterSel.innerHTML = '<option value="">همه دسته‌بندی‌ها</option>' + opts;
      filterSel.value = current;
    }

    const parentSel = document.getElementById("catParentSelect");
    if (parentSel) {
      const current = parentSel.value;
      parentSel.innerHTML = '<option value="">دسته‌بندی اصلی (سطح بالا)</option>' + opts;
      parentSel.value = current;
    }

    const fieldCat = document.getElementById("fieldCategory");
    if (fieldCat) {
      const current = fieldCat.value;
      fieldCat.innerHTML = '<option value="">— انتخاب کنید —</option>' + opts;
      fieldCat.value = current;
      renderSubcatChips(fieldCat.value ? parseInt(fieldCat.value, 10) : null);
    }
  }

  let catRemoveIconFlag = false;
  let catRemovePosterFlag = false;

  function wireCategoryModal() {
    const openBtns = document.querySelectorAll("#manageCategoriesBtn, #editorManageCategoriesBtn");
    const modal = document.getElementById("categoryModal");
    const closeBtn = document.getElementById("categoryModalCloseBtn");
    openBtns.forEach((openBtn) => openBtn.addEventListener("click", () => modal.classList.add("is-open")));
    if (closeBtn) closeBtn.addEventListener("click", () => { modal.classList.remove("is-open"); resetToAddMode(); });
    if (modal) {
      modal.addEventListener("click", (e) => {
        if (e.target === modal) { modal.classList.remove("is-open"); resetToAddMode(); }
      });
    }

    const parentSelect = document.getElementById("catParentSelect");
    const mainExtra = document.getElementById("catMainExtra");
    function toggleMainExtra() {
      if (!mainExtra) return;
      mainExtra.style.display = parentSelect && parentSelect.value ? "none" : "block";
    }
    parentSelect?.addEventListener("change", toggleMainExtra);
    toggleMainExtra();

    wireImagePicker("catIconDrop", "catIconInput", "catIconPreview", "catIconPlaceholder", (f) => {
      catIconFile = f;
      catRemoveIconFlag = false;
      document.getElementById("catRemoveIconBtn").style.display = "inline-block";
    });
    wireImagePicker("catPosterDrop", "catPosterInput", "catPosterPreview", "catPosterPlaceholder", (f) => {
      catPosterFile = f;
      catRemovePosterFlag = false;
      document.getElementById("catRemovePosterBtn").style.display = "inline-block";
    });

    document.getElementById("catRemoveIconBtn")?.addEventListener("click", () => {
      catIconFile = null;
      catRemoveIconFlag = true;
      document.getElementById("catIconInput").value = "";
      document.getElementById("catIconPreview").style.display = "none";
      document.getElementById("catIconPlaceholder").style.display = "block";
      document.getElementById("catRemoveIconBtn").style.display = "none";
    });
    document.getElementById("catRemovePosterBtn")?.addEventListener("click", () => {
      catPosterFile = null;
      catRemovePosterFlag = true;
      document.getElementById("catPosterInput").value = "";
      document.getElementById("catPosterPreview").style.display = "none";
      document.getElementById("catPosterPlaceholder").style.display = "block";
      document.getElementById("catRemovePosterBtn").style.display = "none";
    });

    document.getElementById("catCancelEditBtn")?.addEventListener("click", resetToAddMode);

    const form = document.getElementById("addCategoryForm");
    const msgEl = document.getElementById("addCategoryMsg");
    if (form) {
      form.addEventListener("submit", async (e) => {
        e.preventDefault();
        msgEl.textContent = "";
        msgEl.className = "form-msg";
        const name = form.name.value.trim();
        const parentId = form.parent_id.value || "";
        const description = document.getElementById("catDescriptionInput")?.value.trim() || "";
        const editingId = document.getElementById("catEditingId").value;

        const fd = new FormData();
        fd.append("name", name);
        fd.append("description", description);

        if (editingId) {
          if (!parentId) {
            if (catIconFile) fd.append("icon_image", catIconFile);
            if (catPosterFile) fd.append("poster_image", catPosterFile);
            if (catRemoveIconFlag) fd.append("remove_icon", "1");
            if (catRemovePosterFlag) fd.append("remove_poster", "1");
          }
          fd.append("id", editingId);
        } else {
          fd.append("parent_id", parentId);
          if (!parentId) {
            if (catIconFile) fd.append("icon_image", catIconFile);
            if (catPosterFile) fd.append("poster_image", catPosterFile);
          }
        }

        try {
          const url = editingId ? "api/categories_edit.php" : "api/categories_add.php";
          const data = await apiPostForm(url, fd);
          if (!data.ok) throw new Error(data.message);
          msgEl.textContent = data.message;
          msgEl.classList.add("is-success");
          resetToAddMode();
          await loadCategories();
        } catch (err) {
          msgEl.textContent = err.message || "خطا در ذخیره‌سازی دسته‌بندی.";
          msgEl.classList.add("is-error");
        }
      });
    }
  }

  function startEditCategory(cat) {
    if (!isSuperAdmin) return;
    const form = document.getElementById("addCategoryForm");
    const parentSelect = document.getElementById("catParentSelect");
    const mainExtra = document.getElementById("catMainExtra");
    if (!form) return;

    document.getElementById("catEditingId").value = cat.id;
    form.name.value = cat.name || "";
    parentSelect.value = cat.parent_id || "";
    parentSelect.disabled = true;
    if (mainExtra) mainExtra.style.display = cat.parent_id ? "none" : "block";

    document.getElementById("catDescriptionInput").value = cat.description || "";

    catIconFile = null;
    catPosterFile = null;
    catRemoveIconFlag = false;
    catRemovePosterFlag = false;

    const iconPreview = document.getElementById("catIconPreview");
    const iconPlaceholder = document.getElementById("catIconPlaceholder");
    const removeIconBtn = document.getElementById("catRemoveIconBtn");
    if (cat.icon_image) {
      iconPreview.src = "../" + cat.icon_image;
      iconPreview.style.display = "block";
      iconPlaceholder.style.display = "none";
      removeIconBtn.style.display = "inline-block";
    } else {
      iconPreview.style.display = "none";
      iconPlaceholder.style.display = "block";
      removeIconBtn.style.display = "none";
    }

    const posterPreview = document.getElementById("catPosterPreview");
    const posterPlaceholder = document.getElementById("catPosterPlaceholder");
    const removePosterBtn = document.getElementById("catRemovePosterBtn");
    if (cat.poster_image) {
      posterPreview.src = "../" + cat.poster_image;
      posterPreview.style.display = "block";
      posterPlaceholder.style.display = "none";
      removePosterBtn.style.display = "inline-block";
    } else {
      posterPreview.style.display = "none";
      posterPlaceholder.style.display = "block";
      removePosterBtn.style.display = "none";
    }

    document.getElementById("catSubmitBtn").textContent = "ذخیره تغییرات";
    document.getElementById("catCancelEditBtn").style.display = "inline-block";
    document.getElementById("addCategoryMsg").textContent = "";
    document.getElementById("addCategoryMsg").className = "form-msg";

    form.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  function resetToAddMode() {
    const form = document.getElementById("addCategoryForm");
    if (!form) return;
    form.reset();
    document.getElementById("catEditingId").value = "";
    document.getElementById("catParentSelect").disabled = false;
    document.getElementById("catSubmitBtn").textContent = "افزودن";
    document.getElementById("catCancelEditBtn").style.display = "none";
    resetCategoryMediaPickers();
    const mainExtra = document.getElementById("catMainExtra");
    if (mainExtra) mainExtra.style.display = "block";
  }

  function wireImagePicker(dropId, inputId, previewId, placeholderId, onSelect) {
    const drop = document.getElementById(dropId);
    const input = document.getElementById(inputId);
    if (!drop || !input) return;
    drop.addEventListener("click", () => input.click());
    input.addEventListener("change", () => {
      const file = input.files[0];
      if (!file) return;
      if (file.size > 6 * 1024 * 1024) {
        showToast("حجم تصویر نباید بیشتر از ۶ مگابایت باشد.", true);
        return;
      }
      onSelect(file);
      const reader = new FileReader();
      reader.onload = (e) => {
        const img = document.getElementById(previewId);
        img.src = e.target.result;
        img.style.display = "block";
        document.getElementById(placeholderId).style.display = "none";
      };
      reader.readAsDataURL(file);
    });
  }

  function resetCategoryMediaPickers() {
    catIconFile = null;
    catPosterFile = null;
    catRemoveIconFlag = false;
    catRemovePosterFlag = false;
    ["catIconPreview", "catPosterPreview"].forEach((id) => {
      const img = document.getElementById(id);
      if (img) { img.style.display = "none"; img.src = ""; }
    });
    document.getElementById("catIconPlaceholder").style.display = "block";
    document.getElementById("catPosterPlaceholder").style.display = "block";
    const removeIconBtn = document.getElementById("catRemoveIconBtn");
    const removePosterBtn = document.getElementById("catRemovePosterBtn");
    if (removeIconBtn) removeIconBtn.style.display = "none";
    if (removePosterBtn) removePosterBtn.style.display = "none";
  }

  async function deleteCategory(id) {
    if (!confirm("حذف این دسته‌بندی، زیردسته‌های آن را نیز حذف می‌کند و وبلاگ‌ها مرتبط بدون دسته‌بندی می‌شوند. ادامه می‌دهید؟")) return;
    try {
      const data = await apiPostJson("api/categories_delete.php", { id });
      if (!data.ok) throw new Error(data.message);
      showToast("دسته‌بندی حذف شد.");
      await loadCategories();
      await loadArticles();
    } catch (err) {
      showToast(err.message || "خطا در حذف دسته‌بندی.", true);
    }
  }

  /* ---------------------------------------------------------
     Articles list
  --------------------------------------------------------- */
  function wireFilters() {
    const search = document.getElementById("articleSearchInput");
    const catFilter = document.getElementById("articleCategoryFilter");
    const statusFilter = document.getElementById("articleStatusFilter");
    const authorFilter = document.getElementById("articleAuthorFilter");
    let debounce;
    const resetAndLoad = () => {
      currentArticlesPage = 1;
      loadArticles();
    };
    if (search) {
      search.addEventListener("input", () => {
        clearTimeout(debounce);
        debounce = setTimeout(resetAndLoad, 350);
      });
    }
    if (catFilter) catFilter.addEventListener("change", resetAndLoad);
    if (statusFilter) statusFilter.addEventListener("change", resetAndLoad);
    if (authorFilter) authorFilter.addEventListener("change", resetAndLoad);

    const newBtn = document.getElementById("newArticleBtn");
    if (newBtn) newBtn.addEventListener("click", () => openEditor(null));
  }

  async function loadArticles(page) {
    if (page) currentArticlesPage = page;
    const tbody = document.querySelector("#articlesTable tbody");
    const q = document.getElementById("articleSearchInput")?.value.trim() || "";
    const categoryId = document.getElementById("articleCategoryFilter")?.value || "";
    const status = document.getElementById("articleStatusFilter")?.value || "";
    const authorId = document.getElementById("articleAuthorFilter")?.value || "";

    const params = new URLSearchParams();
    if (q) params.set("q", q);
    if (categoryId) params.set("category_id", categoryId);
    if (status) params.set("status", status);
    if (authorId) params.set("author_id", authorId);
    params.set("page", String(currentArticlesPage));

    try {
      const data = await apiGet("api/articles_list.php?" + params.toString());
      if (!data.ok) throw new Error(data.message);
      articlesCache = data.data.articles;
      renderArticlesTable(articlesCache);
      renderArticlesPagination(data.data);
      const toFa = (n) => n.toLocaleString("fa-IR");
      document.getElementById("artTotalCount").textContent = toFa(data.data.total_count);
      document.getElementById("artPublishedCount").textContent = toFa(data.data.published_count);
      document.getElementById("artDraftCount").textContent = toFa(data.data.draft_count);
    } catch (err) {
      if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="empty-state">خطا در بارگذاری وبلاگ‌ها.</td></tr>';
    }
  }

  function renderArticlesPagination(data) {
    const el = document.getElementById("articlesPagination");
    if (!el) return;
    const page = data.page || 1;
    const totalPages = data.total_pages || 1;
    if (totalPages <= 1) {
      el.innerHTML = "";
      return;
    }
    el.innerHTML = renderPaginationButtons(page, totalPages);
    el.querySelectorAll("[data-page]").forEach((btn) => {
      btn.addEventListener("click", () => loadArticles(parseInt(btn.dataset.page, 10)));
    });
  }

  /**
   * تولید دکمه‌های صفحه‌بندی مشترک (قبلی/بعدی + شماره صفحات نزدیک به صفحه فعلی).
   */
  function renderPaginationButtons(page, totalPages) {
    let html = `<button class="admin-pagination-btn" data-page="${page - 1}" ${page <= 1 ? "disabled" : ""}>قبلی</button>`;
    const windowSize = 2;
    const start = Math.max(1, page - windowSize);
    const end = Math.min(totalPages, page + windowSize);
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
    return html;
  }

  function renderArticlesTable(list) {
    const tbody = document.querySelector("#articlesTable tbody");
    if (!list.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="empty-state">هنوز وبلاگی ثبت نشده است. اولین وبلاگ را بسازید!</td></tr>';
      return;
    }
    tbody.innerHTML = list
      .map((a) => {
        const thumb = a.cover_image
          ? `<img class="art-thumb" src="../${a.cover_image}" alt="">`
          : `<span class="art-thumb art-thumb--empty">—</span>`;
        const cat = a.category_name
          ? `<span class="cat-badge">${escapeHtml(a.category_name)}</span>`
          : `<span class="cat-badge cat-badge--empty">بدون دسته</span>`;
        const statusBadge =
          a.status === "published"
            ? '<span class="status-badge status-badge--published">منتشر شده</span>'
            : '<span class="status-badge status-badge--draft">پیش‌نویس</span>';
        const previewBtn =
          a.status === "published"
            ? `<a class="icon-btn" target="_blank" href="../article.html?slug=${encodeURIComponent(a.slug)}" title="مشاهده در سایت"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12s3.5-7 9-7 9 7 9 7-3.5 7-9 7-9-7-9-7Z"/><circle cx="12" cy="12" r="3"/></svg></a>`
            : "";
        return `
        <tr data-id="${a.id}">
          <td>${thumb}</td>
          <td class="art-title-cell">
            <div>
              <div class="art-title-text"><a href="#" data-action="edit" data-id="${a.id}">${escapeHtml(a.title)}</a></div>
            </div>
          </td>
          <td>${cat}</td>
          <td>${escapeHtml(a.author || "—")}</td>
          <td>${statusBadge}</td>
          <td>${formatDate(a.published_at)}</td>
          <td>${(a.views || 0).toLocaleString("fa-IR")}</td>
          <td>
            <div class="art-actions">
              ${previewBtn}
              <button class="icon-btn" data-action="edit" data-id="${a.id}" title="ویرایش">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L18.5 9.5a2 2 0 0 0-4-4L4 16v4Z"/><path d="M13 6.5 17.5 11"/></svg>
              </button>
              <button class="icon-btn icon-btn--danger" data-action="delete" data-id="${a.id}" title="حذف">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M9 7V4.5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1V7"/><path d="M6 7l1 13a1.5 1.5 0 0 0 1.5 1.4h7a1.5 1.5 0 0 0 1.5-1.4L18 7"/></svg>
              </button>
            </div>
          </td>
        </tr>`;
      })
      .join("");

    tbody.querySelectorAll('[data-action="edit"]').forEach((el) => {
      el.addEventListener("click", (e) => {
        e.preventDefault();
        openEditor(parseInt(el.dataset.id, 10));
      });
    });
    tbody.querySelectorAll('[data-action="delete"]').forEach((el) => {
      el.addEventListener("click", () => deleteArticle(parseInt(el.dataset.id, 10)));
    });
  }

  async function deleteArticle(id) {
    if (!confirm("آیا از حذف این وبلاگ مطمئن هستید؟ این عملیات قابل بازگشت نیست.")) return;
    try {
      const data = await apiPostJson("api/articles_delete.php", { id });
      if (!data.ok) throw new Error(data.message);
      showToast("وبلاگ حذف شد.");
      await loadArticles();
    } catch (err) {
      showToast(err.message || "خطا در حذف وبلاگ.", true);
    }
  }

  /* ---------------------------------------------------------
     Editor shell: open / close / reset
  --------------------------------------------------------- */
  function wireEditorShell() {
    document.getElementById("editorCloseBtn")?.addEventListener("click", closeEditorConfirm);
    document.getElementById("editorCancelBtn")?.addEventListener("click", closeEditorConfirm);
    document.getElementById("editorPreviewBtn")?.addEventListener("click", openArticlePreview);
    document.getElementById("editorPublishBtn")?.addEventListener("click", () => saveArticle("published"));
    document.getElementById("editorSaveDraftBtn")?.addEventListener("click", () => saveArticle("draft"));

    document.getElementById("fieldCategory")?.addEventListener("change", (e) => {
      const val = e.target.value ? parseInt(e.target.value, 10) : null;
      selectedSubcatIds.clear();
      renderSubcatChips(val);
    });
  }

  function closeEditorConfirm() {
    document.getElementById("articleEditorBackdrop").classList.remove("is-open");
  }

  function openEditor(id) {
    resetEditorForm();
    const backdrop = document.getElementById("articleEditorBackdrop");
    backdrop.classList.add("is-open");
    currentEditId = id;

    if (id) {
      document.getElementById("editorTitle").textContent = "در حال بارگذاری…";
      apiGet("api/articles_get.php?id=" + id)
        .then((data) => {
          if (!data.ok) throw new Error(data.message);
          fillEditorForm(data.data.article);
        })
        .catch((err) => {
          showToast(err.message || "خطا در بارگذاری وبلاگ.", true);
          closeEditorConfirm();
        });
    } else {
      document.getElementById("editorTitle").textContent = "وبلاگ جدید";
      setStatusButtonLabels("draft");
    }
    updateSeoPreview();
  }

  function setStatusButtonLabels(status) {
    const draftBtn = document.getElementById("editorSaveDraftBtn");
    const pubBtn = document.getElementById("editorPublishBtn");
    if (status === "published") {
      draftBtn.textContent = "تبدیل به پیش‌نویس";
      pubBtn.textContent = "بروزرسانی وبلاگ";
    } else {
      draftBtn.textContent = "ذخیره پیش‌نویس";
      pubBtn.textContent = "انتشار وبلاگ";
    }
  }

  function resetEditorForm() {
    currentEditId = null;
    currentTags = [];
    selectedSubcatIds = new Set();
    currentCoverFile = null;
    removeCoverFlag = false;
    existingCoverPath = null;
    slugManuallyEdited = false;
    isHtmlSourceMode = false;

    document.getElementById("fieldTitle").value = "";
    document.getElementById("fieldSlug").value = "";
    document.getElementById("fieldExcerpt").value = "";
    document.getElementById("rteEditable").innerHTML = "";
    document.getElementById("rteHtmlView").value = "";
    document.getElementById("rteHtmlView").style.display = "none";
    document.getElementById("rteEditable").style.display = "block";
    document.getElementById("fieldCategory").value = "";
    renderSubcatChips(null);
    renderTagChips();
    document.getElementById("fieldAuthor").value = String(currentAdminId);
    document.getElementById("fieldPublishedAt").value = "";
    document.getElementById("fieldReadingTime").value = "";
    document.getElementById("fieldMetaTitle").value = "";
    document.getElementById("fieldMetaDescription").value = "";
    document.getElementById("fieldFocusKeyword").value = "";
    document.getElementById("coverPreview").style.display = "none";
    document.getElementById("coverPreview").src = "";
    document.getElementById("coverPlaceholder").style.display = "block";
    document.getElementById("removeCoverBtn").style.display = "none";
    document.getElementById("articleFormMsg").textContent = "";
    setStatusButtonLabels("draft");
    updateCharCounts();
  }

  function fillEditorForm(article) {
    document.getElementById("editorTitle").textContent = "ویرایش: " + article.title;
    document.getElementById("fieldTitle").value = article.title || "";
    document.getElementById("fieldSlug").value = article.slug || "";
    slugManuallyEdited = true;
    document.getElementById("fieldExcerpt").value = article.excerpt || "";
    document.getElementById("rteEditable").innerHTML = prepareContentForEditing(article.content || "");
    document.getElementById("fieldCategory").value = article.category_id || "";
    renderSubcatChips(article.category_id || null, article.subcategory_ids || []);
    currentTags = (article.tags || []).slice();
    renderTagChips();
    document.getElementById("fieldAuthor").value = article.author_admin_id != null ? String(article.author_admin_id) : "";
    document.getElementById("fieldPublishedAt").value = toLocalDatetimeValue(article.published_at);
    document.getElementById("fieldReadingTime").value = article.reading_time || "";
    document.getElementById("fieldMetaTitle").value = article.meta_title || "";
    document.getElementById("fieldMetaDescription").value = article.meta_description || "";
    document.getElementById("fieldFocusKeyword").value = article.focus_keyword || "";

    existingCoverPath = article.cover_image || null;
    if (existingCoverPath) {
      const img = document.getElementById("coverPreview");
      img.src = "../" + existingCoverPath;
      img.style.display = "block";
      document.getElementById("coverPlaceholder").style.display = "none";
      document.getElementById("removeCoverBtn").style.display = "inline-block";
    }

    setStatusButtonLabels(article.status);
    updateCharCounts();
    updateSeoPreview();
  }

  function toLocalDatetimeValue(mysqlDateTime) {
    if (!mysqlDateTime) return "";
    return mysqlDateTime.replace(" ", "T").slice(0, 16);
  }

  /* ---------------------------------------------------------
     Subcategory chips
  --------------------------------------------------------- */
  function renderSubcatChips(mainCategoryId, preSelected) {
    const wrap = document.getElementById("subcatsList");
    if (!wrap) return;
    if (preSelected) selectedSubcatIds = new Set(preSelected);

    if (!mainCategoryId) {
      wrap.innerHTML = '<span class="empty-hint">ابتدا دسته‌بندی اصلی را انتخاب کنید</span>';
      return;
    }
    const subs = subcategoriesOf(mainCategoryId);
    if (!subs.length) {
      wrap.innerHTML = '<span class="empty-hint">این دسته‌بندی زیردسته‌ای ندارد</span>';
      return;
    }
    wrap.innerHTML = subs
      .map(
        (s) => `
        <label class="subcat-chip ${selectedSubcatIds.has(s.id) ? "is-checked" : ""}" data-id="${s.id}">
          <input type="checkbox" ${selectedSubcatIds.has(s.id) ? "checked" : ""}>
          ${escapeHtml(s.name)}
        </label>`
      )
      .join("");
    wrap.querySelectorAll(".subcat-chip").forEach((chip) => {
      chip.addEventListener("click", (e) => {
        e.preventDefault();
        const id = parseInt(chip.dataset.id, 10);
        if (selectedSubcatIds.has(id)) {
          selectedSubcatIds.delete(id);
          chip.classList.remove("is-checked");
          chip.querySelector("input").checked = false;
        } else {
          selectedSubcatIds.add(id);
          chip.classList.add("is-checked");
          chip.querySelector("input").checked = true;
        }
      });
    });
  }

  /* ---------------------------------------------------------
     Tags input
  --------------------------------------------------------- */
  function wireTagsInput() {
    const input = document.getElementById("tagInputField");
    if (!input) return;
    input.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === "+") {
        e.preventDefault();
        addTagFromInput();
      } else if (e.key === "Backspace" && input.value === "" && currentTags.length) {
        currentTags.pop();
        renderTagChips();
      }
    });
  }

  function addTagFromInput() {
    const input = document.getElementById("tagInputField");
    let val = input.value.trim().replace(/^#/, "");
    if (!val) return;
    if (val.length > 40) val = val.slice(0, 40);
    if (!currentTags.some((t) => t.toLowerCase() === val.toLowerCase()) && currentTags.length < 20) {
      currentTags.push(val);
      renderTagChips();
    }
    input.value = "";
  }

  function renderTagChips() {
    const wrap = document.getElementById("tagsInput");
    const input = document.getElementById("tagInputField");
    wrap.querySelectorAll(".tag-chip").forEach((el) => el.remove());
    currentTags.forEach((tag, i) => {
      const chip = document.createElement("span");
      chip.className = "tag-chip";
      chip.innerHTML = `${escapeHtml(tag)} <button type="button" data-i="${i}">&times;</button>`;
      chip.querySelector("button").addEventListener("click", () => {
        currentTags.splice(i, 1);
        renderTagChips();
      });
      wrap.insertBefore(chip, input);
    });
  }

  /* ---------------------------------------------------------
     Cover image
  --------------------------------------------------------- */
  function wireCoverUpload() {
    const drop = document.getElementById("coverDrop");
    const fileInput = document.getElementById("coverFileInput");
    const removeBtn = document.getElementById("removeCoverBtn");
    if (!drop) return;

    drop.addEventListener("click", () => fileInput.click());
    fileInput.addEventListener("change", () => {
      const file = fileInput.files[0];
      if (!file) return;
      if (file.size > 6 * 1024 * 1024) {
        showToast("حجم تصویر نباید بیشتر از ۶ مگابایت باشد.", true);
        return;
      }
      currentCoverFile = file;
      removeCoverFlag = false;
      const reader = new FileReader();
      reader.onload = (e) => {
        const img = document.getElementById("coverPreview");
        img.src = e.target.result;
        img.style.display = "block";
        document.getElementById("coverPlaceholder").style.display = "none";
        removeBtn.style.display = "inline-block";
      };
      reader.readAsDataURL(file);
    });

    removeBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      currentCoverFile = null;
      removeCoverFlag = true;
      fileInput.value = "";
      document.getElementById("coverPreview").style.display = "none";
      document.getElementById("coverPlaceholder").style.display = "block";
      removeBtn.style.display = "none";
    });
  }

  /* ---------------------------------------------------------
     Slug auto-generation preview
  --------------------------------------------------------- */
  function wireSlugTracking() {
    document.getElementById("fieldSlug")?.addEventListener("input", () => {
      slugManuallyEdited = true;
      updateSeoPreview();
    });
    document.getElementById("fieldTitle")?.addEventListener("input", (e) => {
      updateSeoPreview();
    });
  }

  /* ---------------------------------------------------------
     SEO preview + character counters
  --------------------------------------------------------- */
  function wireSeoCounters() {
    ["fieldMetaTitle", "fieldMetaDescription", "fieldFocusKeyword", "fieldSlug"].forEach((id) => {
      document.getElementById(id)?.addEventListener("input", () => {
        updateCharCounts();
        updateSeoPreview();
      });
    });
  }

  function updateCharCounts() {
    const mt = document.getElementById("fieldMetaTitle").value.length;
    const md = document.getElementById("fieldMetaDescription").value.length;
    document.getElementById("metaTitleCount").textContent = `${mt.toLocaleString("fa-IR")}/۶۰`;
    document.getElementById("metaDescCount").textContent = `${md.toLocaleString("fa-IR")}/۱۶۰`;
  }

  function updateSeoPreview() {
    const title = document.getElementById("fieldMetaTitle").value.trim() || document.getElementById("fieldTitle").value.trim() || "عنوان وبلاگ در نتایج گوگل";
    const desc = document.getElementById("fieldMetaDescription").value.trim() || document.getElementById("fieldExcerpt").value.trim() || "توضیحات متا اینجا نمایش داده می‌شود...";
    const slug = document.getElementById("fieldSlug").value.trim() || "your-article-slug";
    document.getElementById("seoPreviewTitle").textContent = title;
    document.getElementById("seoPreviewDesc").textContent = desc;
    document.getElementById("seoPreviewSlug").textContent = slug;
    document.getElementById("seoPreviewDomain").textContent = window.location.host;
  }

  /* ---------------------------------------------------------
     Rich text editor
  --------------------------------------------------------- */
  /**
   * تشخیص لینک ویدیو (آپارات، یوتیوب یا فایل مستقیم) و ساخت کد جاسازی مناسب.
   */
  function buildVideoEmbedHtml(url) {
    try {
      const u = new URL(url);
      const host = u.hostname.replace(/^www\./, "");

      // یوتیوب
      if (host === "youtube.com" || host === "m.youtube.com") {
        let videoId = u.searchParams.get("v");
        if (!videoId && u.pathname.startsWith("/shorts/")) videoId = u.pathname.split("/")[2];
        if (!videoId && u.pathname.startsWith("/embed/")) videoId = u.pathname.split("/")[2];
        if (videoId) {
          return `<iframe src="https://www.youtube.com/embed/${videoId}" allowfullscreen loading="lazy" style="width:100%;aspect-ratio:16/9;border:0;border-radius:14px;"></iframe><p><br></p>`;
        }
      }
      if (host === "youtu.be") {
        const videoId = u.pathname.replace("/", "");
        if (videoId) {
          return `<iframe src="https://www.youtube.com/embed/${videoId}" allowfullscreen loading="lazy" style="width:100%;aspect-ratio:16/9;border:0;border-radius:14px;"></iframe><p><br></p>`;
        }
      }

      // آپارات — الگوهای رایج: aparat.com/v/HASH یا aparat.com/video/video/embed/videohash/HASH/vt/frame
      if (host === "aparat.com") {
        if (u.pathname.includes("/embed/")) {
          return `<iframe src="${u.toString()}" allowfullscreen loading="lazy" style="width:100%;aspect-ratio:16/9;border:0;border-radius:14px;"></iframe><p><br></p>`;
        }
        const parts = u.pathname.split("/").filter(Boolean); // ["v", "HASH"]
        const vIndex = parts.indexOf("v");
        const hash = vIndex !== -1 ? parts[vIndex + 1] : null;
        if (hash) {
          return `<iframe src="https://www.aparat.com/video/video/embed/videohash/${hash}/vt/frame" allowfullscreen loading="lazy" style="width:100%;aspect-ratio:16/9;border:0;border-radius:14px;"></iframe><p><br></p>`;
        }
      }

      // فایل ویدیوی مستقیم (mp4, webm, ogg و ...)
      if (/\.(mp4|webm|ogg|mov)(\?.*)?$/i.test(u.pathname)) {
        return `<video src="${u.toString()}" controls style="max-width:100%;border-radius:14px;"></video><p><br></p>`;
      }

      return null;
    } catch (e) {
      return null;
    }
  }

  /**
   * افزودن لینک واقعی و قابل‌کلیک: چه متنی از قبل انتخاب شده باشد چه نه،
   * از کاربر آدرس و متن لینک را می‌گیرد و یک <a> واقعی درج می‌کند که با
   * کلیک روی متن، لینک باز می‌شود.
   */
  function insertRteLink(editable) {
    const selectedText = (window.getSelection ? window.getSelection().toString() : "").trim();
    const url = prompt("آدرس لینک را وارد کنید:", "https://");
    if (!url) return;
    const trimmedUrl = url.trim();
    if (!trimmedUrl) return;
    const linkText = prompt("متنی که روی آن کلیک می‌شود چه باشد؟", selectedText || trimmedUrl);
    if (linkText === null) return;
    const finalText = linkText.trim() || trimmedUrl;
    editable.focus();
    const html = `<a href="${escapeHtml(trimmedUrl)}" target="_blank" rel="noopener">${escapeHtml(finalText)}</a>`;
    document.execCommand("insertHTML", false, html);
  }

  function wireToolbar() {
    const toolbar = document.getElementById("rteToolbar");
    const editable = document.getElementById("rteEditable");
    if (!toolbar || !editable) return;

    toolbar.querySelectorAll("button[data-cmd]").forEach((btn) => {
      btn.addEventListener("click", () => {
        editable.focus();
        const cmd = btn.dataset.cmd;
        if (cmd === "blockquote") {
          document.execCommand("formatBlock", false, "blockquote");
        } else {
          document.execCommand(cmd, false, null);
        }
      });
    });

    toolbar.querySelectorAll("button[data-block]").forEach((btn) => {
      btn.addEventListener("click", () => {
        editable.focus();
        document.execCommand("formatBlock", false, btn.dataset.block);
      });
    });

    document.getElementById("rteTextColor")?.addEventListener("input", (e) => {
      editable.focus();
      document.execCommand("foreColor", false, e.target.value);
    });
    document.getElementById("rteHiliteColor")?.addEventListener("input", (e) => {
      editable.focus();
      document.execCommand("hiliteColor", false, e.target.value);
    });

    document.getElementById("rteLinkBtn")?.addEventListener("click", () => {
      insertRteLink(editable);
    });

    const imageFileInput = document.getElementById("rteImageFile");
    document.getElementById("rteImageBtn")?.addEventListener("click", () => imageFileInput.click());
    imageFileInput?.addEventListener("change", async () => {
      const file = imageFileInput.files[0];
      if (!file) return;
      if (file.size > 6 * 1024 * 1024) {
        showToast("حجم تصویر نباید بیشتر از ۶ مگابایت باشد.", true);
        return;
      }
      const fd = new FormData();
      fd.append("image", file);
      showToast("در حال آپلود تصویر...");
      try {
        const data = await apiPostForm("api/upload_image.php", fd);
        if (!data.ok) throw new Error(data.message);
        editable.focus();
        const img = `<img src="../${data.data.url}" data-src="${data.data.url}" alt="">`;
        document.execCommand("insertHTML", false, img);
      } catch (err) {
        showToast(err.message || "خطا در آپلود تصویر.", true);
      }
      imageFileInput.value = "";
    });

    const videoFileInput = document.getElementById("rteVideoFile");
    document.getElementById("rteVideoBtn")?.addEventListener("click", () => {
      const link = prompt("لینک ویدیو آپارات یا یوتیوب را وارد کنید.\nبرای آپلود مستقیم فایل ویدیو از روی سیستم، این کادر را خالی بگذارید و «OK» را بزنید.", "");
      if (link === null) return; // انصراف
      const trimmed = link.trim();
      if (trimmed === "") {
        videoFileInput.click();
        return;
      }
      const embedHtml = buildVideoEmbedHtml(trimmed);
      if (!embedHtml) {
        showToast("این لینک شناسایی نشد. لینک آپارات، یوتیوب یا فایل مستقیم ویدیو را وارد کنید.", true);
        return;
      }
      editable.focus();
      document.execCommand("insertHTML", false, embedHtml);
    });
    videoFileInput?.addEventListener("change", async () => {
      const file = videoFileInput.files[0];
      if (!file) return;
      if (file.size > 60 * 1024 * 1024) {
        showToast("حجم ویدیو نباید بیشتر از ۶۰ مگابایت باشد.", true);
        return;
      }
      const fd = new FormData();
      fd.append("media", file);
      fd.append("kind", "video");
      fd.append("section", "articles");
      showToast("در حال آپلود ویدیو...");
      try {
        const data = await apiPostForm("api/upload_media.php", fd);
        if (!data.ok) throw new Error(data.message);
        editable.focus();
        document.execCommand(
          "insertHTML", false,
          `<video src="../${data.data.url}" data-src="${data.data.url}" controls style="max-width:100%;border-radius:14px;"></video>`
        );
      } catch (err) {
        showToast(err.message || "خطا در آپلود ویدیو.", true);
      }
      videoFileInput.value = "";
    });

    const audioFileInput = document.getElementById("rteAudioFile");
    document.getElementById("rteAudioBtn")?.addEventListener("click", () => audioFileInput.click());
    audioFileInput?.addEventListener("change", async () => {
      const file = audioFileInput.files[0];
      if (!file) return;
      if (file.size > 25 * 1024 * 1024) {
        showToast("حجم فایل صوتی نباید بیشتر از ۲۵ مگابایت باشد.", true);
        return;
      }
      const fd = new FormData();
      fd.append("media", file);
      fd.append("kind", "audio");
      fd.append("section", "articles");
      showToast("در حال آپلود فایل صوتی...");
      try {
        const data = await apiPostForm("api/upload_media.php", fd);
        if (!data.ok) throw new Error(data.message);
        editable.focus();
        document.execCommand(
          "insertHTML", false,
          `<audio src="../${data.data.url}" data-src="${data.data.url}" controls style="width:100%;"></audio>`
        );
      } catch (err) {
        showToast(err.message || "خطا در آپلود فایل صوتی.", true);
      }
      audioFileInput.value = "";
    });

    document.getElementById("rteHtmlToggleBtn")?.addEventListener("click", toggleHtmlSourceMode);

    document.getElementById("rteHrBtn")?.addEventListener("click", () => {
      editable.focus();
      document.execCommand("insertHTML", false, "<hr>");
    });

    document.getElementById("rteCodeBtn")?.addEventListener("click", () => {
      editable.focus();
      document.execCommand("formatBlock", false, "PRE");
    });

    document.getElementById("rteTableBtn")?.addEventListener("click", () => {
      const rowsInput = prompt("تعداد ردیف‌های جدول (بدون سرستون):", "3");
      if (rowsInput === null) return;
      const colsInput = prompt("تعداد ستون‌های جدول:", "3");
      if (colsInput === null) return;
      const rows = Math.min(Math.max(parseInt(rowsInput, 10) || 0, 1), 30);
      const cols = Math.min(Math.max(parseInt(colsInput, 10) || 0, 1), 12);
      editable.focus();
      document.execCommand("insertHTML", false, buildTableHtml(rows, cols));
    });

    wireEmojiPanel(document.getElementById("rteEmojiBtn"), document.getElementById("rteEmojiPanel"), editable);
  }

  /**
   * ساخت جدول HTML قابل‌ویرایش با یک ردیف سرستون.
   */
  function buildTableHtml(rows, cols) {
    let head = "<tr>";
    for (let c = 0; c < cols; c++) head += `<th>ستون ${c + 1}</th>`;
    head += "</tr>";
    let body = "";
    for (let r = 0; r < rows; r++) {
      body += "<tr>";
      for (let c = 0; c < cols; c++) body += "<td>&nbsp;</td>";
      body += "</tr>";
    }
    return `<table><thead>${head}</thead><tbody>${body}</tbody></table><p><br></p>`;
  }

  /**
   * پنل ایموجی: چون rte-toolbar/rte دارای overflow:hidden است، پنل را به body
   * منتقل می‌کنیم و موقع باز شدن، جای آن را دقیقاً زیر دکمه محاسبه می‌کنیم.
   */
  function wireEmojiPanel(btn, panel, editable) {
    if (!btn || !panel) return;
    if (panel.parentElement !== document.body) document.body.appendChild(panel);

    function closePanel() {
      panel.classList.remove("is-open");
    }
    function positionPanel() {
      const rect = btn.getBoundingClientRect();
      const panelWidth = Math.min(280, window.innerWidth - 24); // هماهنگ با max-width در CSS
      let left = rect.left;
      if (left + panelWidth > window.innerWidth - 12) left = window.innerWidth - panelWidth - 12;
      if (left < 12) left = 12;
      panel.style.top = `${rect.bottom + 6}px`;
      panel.style.left = `${left}px`;
    }

    btn.addEventListener("click", (e) => {
      e.stopPropagation();
      const willOpen = !panel.classList.contains("is-open");
      document.querySelectorAll(".rte-emoji-panel.is-open").forEach((p) => p.classList.remove("is-open"));
      if (willOpen) {
        positionPanel();
        panel.classList.add("is-open");
      }
    });

    panel.querySelectorAll(".rte-emoji-item").forEach((item) => {
      item.addEventListener("click", () => {
        editable.focus();
        document.execCommand("insertText", false, item.dataset.emoji);
        closePanel();
      });
    });

    document.addEventListener("click", (e) => {
      if (panel.classList.contains("is-open") && !panel.contains(e.target) && e.target !== btn) closePanel();
    });
    window.addEventListener("resize", () => { if (panel.classList.contains("is-open")) positionPanel(); });
  }

  function toggleHtmlSourceMode() {
    const editable = document.getElementById("rteEditable");
    const htmlView = document.getElementById("rteHtmlView");
    if (!isHtmlSourceMode) {
      htmlView.value = prepareContentForSaving(editable.innerHTML);
      editable.style.display = "none";
      htmlView.style.display = "block";
    } else {
      editable.innerHTML = prepareContentForEditing(htmlView.value);
      htmlView.style.display = "none";
      editable.style.display = "block";
    }
    isHtmlSourceMode = !isHtmlSourceMode;
  }

  /**
   * محتوای ذخیره‌شده در دیتابیس همیشه با مسیر نسبی‌به‌ریشه سایت است
   * (مثلاً assets/uploads/articles/content/x.jpg) چون هم در سایت و هم
   * در پیش‌نمایش پنل (که داخل پوشه admin/ است) قابل استفاده باشد،
   * برای نمایش داخل پنل با "../" پیشوند می‌گیرد و data-src مسیر اصلی را نگه می‌دارد.
   */
  function prepareContentForEditing(html) {
    const div = document.createElement("div");
    div.innerHTML = html;
    div.querySelectorAll("img, video, audio").forEach((el) => {
      const src = el.getAttribute("src") || "";
      if (src.startsWith("assets/uploads/")) {
        el.setAttribute("data-src", src);
        el.setAttribute("src", "../" + src);
      }
    });
    return div.innerHTML;
  }

  function prepareContentForSaving(html) {
    const div = document.createElement("div");
    div.innerHTML = html;
    div.querySelectorAll("[data-src]").forEach((el) => {
      el.setAttribute("src", el.getAttribute("data-src"));
      el.removeAttribute("data-src");
    });
    return div.innerHTML;
  }

  function getFinalContentHtml() {
    if (isHtmlSourceMode) {
      return prepareContentForSaving(document.getElementById("rteHtmlView").value);
    }
    return prepareContentForSaving(document.getElementById("rteEditable").innerHTML);
  }

  /* ---------------------------------------------------------
     Save
  --------------------------------------------------------- */
  /**
   * محتوای فعلی فرم (چه ذخیره شده باشد چه نه) را به preview_save.php می‌فرستد
   * و صفحه‌ی واقعی وبلاگ را در یک تب جدید، در «حالت پیش‌نمایش» باز می‌کند.
   */
  async function openArticlePreview() {
    const title = document.getElementById("fieldTitle").value.trim();
    if (title.length < 3) {
      showToast("برای پیش‌نمایش، عنوان وبلاگ را وارد کنید.", true);
      return;
    }

    const catId = document.getElementById("fieldCategory").value;
    const cat = catId ? categoriesCache.find((c) => String(c.id) === String(catId)) : null;
    const authorSel = document.getElementById("fieldAuthor");
    const coverImg = document.getElementById("coverPreview");
    const coverSrc = coverImg && coverImg.style.display !== "none" ? coverImg.src : "";

    const payload = {
      type: "article",
      title,
      slug: document.getElementById("fieldSlug").value.trim(),
      excerpt: document.getElementById("fieldExcerpt").value.trim(),
      content: getFinalContentHtml(),
      author: authorSel?.selectedOptions[0]?.textContent || "",
      category_name: cat?.name || "",
      category_slug: cat?.slug || "",
      published_at: document.getElementById("fieldPublishedAt").value,
      reading_time: document.getElementById("fieldReadingTime").value,
      meta_title: document.getElementById("fieldMetaTitle").value.trim(),
      meta_description: document.getElementById("fieldMetaDescription").value.trim(),
      tags: currentTags,
      cover_image: coverSrc,
    };

    try {
      const data = await apiPostJson("api/preview_save.php", payload);
      if (!data.ok) throw new Error(data.message);
      window.open("../article.html?preview_token=" + encodeURIComponent(data.data.token), "_blank");
    } catch (err) {
      showToast(err.message || "خطا در ساخت پیش‌نمایش.", true);
    }
  }

  async function saveArticle(status) {
    const title = document.getElementById("fieldTitle").value.trim();
    if (title.length < 3) {
      showToast("عنوان وبلاگ باید حداقل ۳ کاراکتر باشد.", true);
      return;
    }

    const msgEl = document.getElementById("articleFormMsg");
    msgEl.textContent = "در حال ذخیره...";

    const fd = new FormData();
    if (currentEditId) fd.append("id", currentEditId);
    fd.append("title", title);
    fd.append("slug", document.getElementById("fieldSlug").value.trim());
    fd.append("excerpt", document.getElementById("fieldExcerpt").value.trim());
    fd.append("content", getFinalContentHtml());
    fd.append("author_admin_id", document.getElementById("fieldAuthor").value);
    fd.append("category_id", document.getElementById("fieldCategory").value);
    fd.append("subcategory_ids", JSON.stringify(Array.from(selectedSubcatIds)));
    fd.append("tags", JSON.stringify(currentTags));
    fd.append("status", status);
    fd.append("published_at", document.getElementById("fieldPublishedAt").value);
    fd.append("reading_time", document.getElementById("fieldReadingTime").value);
    fd.append("meta_title", document.getElementById("fieldMetaTitle").value.trim());
    fd.append("meta_description", document.getElementById("fieldMetaDescription").value.trim());
    fd.append("focus_keyword", document.getElementById("fieldFocusKeyword").value.trim());
    if (currentCoverFile) fd.append("cover_image", currentCoverFile);
    if (removeCoverFlag) fd.append("remove_cover", "1");

    try {
      const data = await apiPostForm("api/articles_save.php", fd);
      if (!data.ok) throw new Error(data.message);
      msgEl.textContent = "";
      showToast(data.message || "وبلاگ ذخیره شد.");
      closeEditorConfirm();
      await loadArticles();
      await loadCategories();
    } catch (err) {
      msgEl.textContent = "";
      showToast(err.message || "خطا در ذخیره‌سازی وبلاگ.", true);
    }
  }
})();
