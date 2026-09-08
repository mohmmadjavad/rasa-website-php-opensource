/* =========================================================
   admin-projects.js — مدیریت پروژه‌ها پنل ادمین رسا
   ========================================================= */
(function () {
  "use strict";

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || "";
  const currentAdminRole = document.querySelector('meta[name="admin-role"]')?.content || "admin";
  const currentAdminId = parseInt(document.querySelector('meta[name="admin-id"]')?.content || "0", 10);
  const isSuperAdmin = currentAdminRole === "super_admin";
  const projectsScope = document.querySelector('meta[name="projects-scope"]')?.content || "own";
  const canSeeAllProjects = isSuperAdmin || projectsScope === "all";

  let categoriesCache = [];
  let selectedCategoryIds = new Set();
  let brandsCache = [];
  let selectedBrandIds = new Set();
  let adminsCache = [];
  let projectsCache = [];
  let currentEditId = null;
  let slugManuallyEdited = false;
  let isHtmlSourceMode = false;

  let coverType = "image";
  let currentCoverImageFile = null;
  let removeCoverFlag = false;
  let existingCoverImage = null;
  let currentCoverVideoFile = null;
  let existingCoverVideo = null;

  let selectedAdminIds = new Set();
  let manualMembers = []; // {clientId, id, name, role_title, avatarFile, existingAvatar, removeAvatar, previewUrl}
  let memberSeq = 0;

  document.addEventListener("DOMContentLoaded", () => {
    if (!document.getElementById("tab-projects")) return;

    const steps = [
      ["wireProjectFilters", wireProjectFilters],
      ["wireProjectEditorShell", wireProjectEditorShell],
      ["wireProjectCategoryModal", wireProjectCategoryModal],
      ["wireProjectBrandModal", wireProjectBrandModal],
      ["wireProjectToolbar", wireProjectToolbar],
      ["wireProjectCoverUpload", wireProjectCoverUpload],
      ["wireProjectSlugTracking", wireProjectSlugTracking],
      ["wireManualMembers", wireManualMembers],
      ["loadAdminsForTeamPicker", loadAdminsForTeamPicker],
      ["loadProjectCategories", loadProjectCategories],
      ["loadProjectBrands", loadProjectBrands],
      ["loadProjects", loadProjects],
    ];
    steps.forEach(([name, fn]) => {
      try {
        fn();
      } catch (err) {
        console.error("[admin-projects] " + name + " failed:", err);
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

  function initialsOf(name) {
    if (!name) return "؟";
    return name.trim().charAt(0);
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
    const res = await fetch(url, { method: "POST", headers: { "X-CSRF-Token": csrfToken }, body: formData });
    return res.json();
  }

  /* ---------------------------------------------------------
     دسته‌بندی‌های پروژه
  --------------------------------------------------------- */
  async function loadProjectCategories() {
    try {
      const data = await apiGet("api/categories_list.php?type=project");
      if (!data.ok) throw new Error(data.message);
      categoriesCache = data.data.categories;
      renderProjectCategorySelects();
      renderProjectCategoryTree();
    } catch (err) {
      const tree = document.getElementById("projectCategoryTree");
      if (tree) tree.innerHTML = '<div class="empty-state">خطا در بارگذاری دسته‌بندی‌ها.</div>';
    }
  }

  function renderProjectCategorySelects() {
    const opts = categoriesCache.map((c) => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join("");
    const filterSel = document.getElementById("projectCategoryFilter");
    if (filterSel) {
      const current = filterSel.value;
      filterSel.innerHTML = '<option value="">همه دسته‌بندی‌ها</option>' + opts;
      filterSel.value = current;
    }
    renderCategoryChips();
  }

  /* ---------------------------------------------------------
     دسته‌بندی‌های پروژه — چیپ‌های چند‌انتخابی
  --------------------------------------------------------- */
  function renderCategoryChips(preSelected) {
    if (preSelected) selectedCategoryIds = new Set(preSelected);
    const wrap = document.getElementById("pCategoriesList");
    if (!wrap) return;

    if (!categoriesCache.length) {
      wrap.innerHTML = '<span class="empty-hint">هنوز دسته‌بندی‌ای ساخته نشده است.</span>';
      return;
    }
    wrap.innerHTML = categoriesCache
      .map(
        (c) => `
        <label class="subcat-chip ${selectedCategoryIds.has(c.id) ? "is-checked" : ""}" data-id="${c.id}">
          <input type="checkbox" ${selectedCategoryIds.has(c.id) ? "checked" : ""}>
          ${escapeHtml(c.name)}
        </label>`
      )
      .join("");
    wrap.querySelectorAll(".subcat-chip").forEach((chip) => {
      chip.addEventListener("click", (e) => {
        e.preventDefault();
        const id = parseInt(chip.dataset.id, 10);
        if (selectedCategoryIds.has(id)) {
          selectedCategoryIds.delete(id);
          chip.classList.remove("is-checked");
          chip.querySelector("input").checked = false;
        } else {
          selectedCategoryIds.add(id);
          chip.classList.add("is-checked");
          chip.querySelector("input").checked = true;
        }
      });
    });
  }

  function renderProjectCategoryTree() {
    const tree = document.getElementById("projectCategoryTree");
    if (!tree) return;
    if (!categoriesCache.length) {
      tree.innerHTML = '<div class="empty-state">هنوز دسته‌بندی‌ای ساخته نشده است.</div>';
      return;
    }
    const canManage = isSuperAdmin;
    tree.innerHTML = categoriesCache
      .map(
        (c) => `
      <div class="cat-row" data-id="${c.id}">
        <div class="cat-row-main">
          <span class="cat-row-name">📁 ${escapeHtml(c.name)} <span class="cat-row-count">(${c.project_count || 0} پروژه)</span></span>
        </div>
        ${
          canManage
            ? `<div class="cat-row-actions">
                 <button class="cat-row-edit" data-id="${c.id}" title="ویرایش">✎</button>
                 <button class="cat-row-del" data-id="${c.id}" title="حذف">&times;</button>
               </div>`
            : ""
        }
      </div>`
      )
      .join("");

    tree.querySelectorAll(".cat-row-del").forEach((btn) => {
      btn.addEventListener("click", () => deleteProjectCategory(parseInt(btn.dataset.id, 10)));
    });
    tree.querySelectorAll(".cat-row-edit").forEach((btn) => {
      btn.addEventListener("click", () => {
        const cat = categoriesCache.find((c) => c.id === parseInt(btn.dataset.id, 10));
        if (cat) startEditProjectCategory(cat);
      });
    });
  }

  function startEditProjectCategory(cat) {
    document.getElementById("pCatEditingId").value = cat.id;
    document.getElementById("pCatNameInput").value = cat.name;
    document.getElementById("pCatSubmitBtn").textContent = "ذخیره تغییرات";
    document.getElementById("pCatCancelEditBtn").style.display = "inline-block";
  }

  function resetProjectCategoryForm() {
    const form = document.getElementById("addProjectCategoryForm");
    if (!form) return;
    form.reset();
    document.getElementById("pCatEditingId").value = "";
    document.getElementById("pCatSubmitBtn").textContent = "افزودن";
    document.getElementById("pCatCancelEditBtn").style.display = "none";
  }

  async function deleteProjectCategory(id) {
    if (!confirm("حذف این دسته‌بندی، پروژه‌های مرتبط را بدون دسته‌بندی می‌کند. ادامه می‌دهید؟")) return;
    try {
      const data = await apiPostJson("api/categories_delete.php", { id });
      if (!data.ok) throw new Error(data.message);
      showToast("دسته‌بندی حذف شد.");
      await loadProjectCategories();
      await loadProjects();
    } catch (err) {
      showToast(err.message || "خطا در حذف دسته‌بندی.", true);
    }
  }

  function wireProjectCategoryModal() {
    const openBtns = document.querySelectorAll("#manageProjectCategoriesBtn, #pEditorManageCategoriesBtn");
    const modal = document.getElementById("projectCategoryModal");
    const closeBtn = document.getElementById("projectCategoryModalCloseBtn");
    openBtns.forEach((openBtn) => openBtn.addEventListener("click", () => modal.classList.add("is-open")));
    if (closeBtn) closeBtn.addEventListener("click", () => { modal.classList.remove("is-open"); resetProjectCategoryForm(); });
    if (modal) {
      modal.addEventListener("click", (e) => {
        if (e.target === modal) { modal.classList.remove("is-open"); resetProjectCategoryForm(); }
      });
    }
    document.getElementById("pCatCancelEditBtn")?.addEventListener("click", resetProjectCategoryForm);

    const form = document.getElementById("addProjectCategoryForm");
    const msgEl = document.getElementById("addProjectCategoryMsg");
    form?.addEventListener("submit", async (e) => {
      e.preventDefault();
      msgEl.textContent = "";
      msgEl.className = "form-msg";
      const name = form.name.value.trim();
      const editingId = document.getElementById("pCatEditingId").value;

      const fd = new FormData();
      fd.append("name", name);
      fd.append("type", "project");
      if (editingId) fd.append("id", editingId);

      try {
        const url = editingId ? "api/categories_edit.php" : "api/categories_add.php";
        const data = await apiPostForm(url, fd);
        if (!data.ok) throw new Error(data.message);
        msgEl.textContent = data.message;
        msgEl.classList.add("is-success");
        resetProjectCategoryForm();
        await loadProjectCategories();
      } catch (err) {
        msgEl.textContent = err.message || "خطا در ذخیره‌سازی دسته‌بندی.";
        msgEl.classList.add("is-error");
      }
    });
  }

  /* ---------------------------------------------------------
     برندهایی که با آن‌ها کار کرده‌ایم — چند‌انتخابی، فقط برای نمایش
     در «صفحه پروژه‌ها» یا «صفحه پروژه‌ها + پروفایل اعضا» فعال است
  --------------------------------------------------------- */
  async function loadProjectBrands() {
    try {
      const data = await apiGet("api/brands_list.php");
      if (!data.ok) throw new Error(data.message);
      brandsCache = data.data.brands;
      renderBrandChips();
      renderBrandTree();
    } catch (err) {
      const tree = document.getElementById("brandTree");
      if (tree) tree.innerHTML = '<div class="empty-state">خطا در بارگذاری برندها.</div>';
    }
  }

  function renderBrandChips(preSelected) {
    if (preSelected) selectedBrandIds = new Set(preSelected);
    const wrap = document.getElementById("pBrandsList");
    if (!wrap) return;

    if (!brandsCache.length) {
      wrap.innerHTML = '<span class="empty-hint">هنوز برندی ساخته نشده است.</span>';
      return;
    }
    wrap.innerHTML = brandsCache
      .map(
        (b) => `
        <label class="subcat-chip ${selectedBrandIds.has(b.id) ? "is-checked" : ""}" data-id="${b.id}">
          <input type="checkbox" ${selectedBrandIds.has(b.id) ? "checked" : ""}>
          ${b.logo_image ? `<img src="${b.logo_image}" alt="" style="width:16px;height:16px;object-fit:contain;border-radius:4px;vertical-align:middle;margin-left:.3rem;">` : ""}
          ${escapeHtml(b.name)}
        </label>`
      )
      .join("");
    wrap.querySelectorAll(".subcat-chip").forEach((chip) => {
      chip.addEventListener("click", (e) => {
        e.preventDefault();
        const id = parseInt(chip.dataset.id, 10);
        if (selectedBrandIds.has(id)) {
          selectedBrandIds.delete(id);
          chip.classList.remove("is-checked");
          chip.querySelector("input").checked = false;
        } else {
          selectedBrandIds.add(id);
          chip.classList.add("is-checked");
          chip.querySelector("input").checked = true;
        }
      });
    });
  }

  /** فیلد برندها فقط وقتی معنا دارد که پروژه در «صفحه پروژه‌ها» نمایش داده شود (با یا بدون پروفایل اعضا). */
  function updateBrandsCardVisibility() {
    const card = document.getElementById("pBrandsCard");
    const scopeSel = document.getElementById("pFieldDisplayScope");
    if (!card || !scopeSel) return;
    const isActive = scopeSel.value !== "profile_only";
    card.style.display = isActive ? "" : "none";
    if (!isActive && selectedBrandIds.size) {
      selectedBrandIds = new Set();
      renderBrandChips();
    }
  }

  function renderBrandTree() {
    const tree = document.getElementById("brandTree");
    if (!tree) return;
    if (!brandsCache.length) {
      tree.innerHTML = '<div class="empty-state">هنوز برندی ساخته نشده است.</div>';
      return;
    }
    const canManage = isSuperAdmin;
    tree.innerHTML = brandsCache
      .map(
        (b) => `
      <div class="cat-row" data-id="${b.id}">
        <div class="cat-row-main">
          ${b.logo_image ? `<img src="${b.logo_image}" alt="" style="width:24px;height:24px;object-fit:contain;border-radius:6px;vertical-align:middle;margin-left:.4rem;">` : ""}
          <span class="cat-row-name">${b.logo_image ? "" : "🏷️ "}${escapeHtml(b.name)} <span class="cat-row-count">(${b.project_count || 0} پروژه)</span></span>
        </div>
        ${
          canManage
            ? `<div class="cat-row-actions">
                 <button class="cat-row-edit" data-id="${b.id}" title="ویرایش">✎</button>
                 <button class="cat-row-del" data-id="${b.id}" title="حذف">&times;</button>
               </div>`
            : ""
        }
      </div>`
      )
      .join("");

    tree.querySelectorAll(".cat-row-del").forEach((btn) => {
      btn.addEventListener("click", () => deleteBrand(parseInt(btn.dataset.id, 10)));
    });
    tree.querySelectorAll(".cat-row-edit").forEach((btn) => {
      btn.addEventListener("click", () => {
        const b = brandsCache.find((x) => x.id === parseInt(btn.dataset.id, 10));
        if (b) startEditBrand(b);
      });
    });
  }

  function startEditBrand(b) {
    document.getElementById("brandEditingId").value = b.id;
    document.getElementById("brandNameInput").value = b.name;
    document.getElementById("brandSubmitBtn").textContent = "ذخیره تغییرات";
    document.getElementById("brandCancelEditBtn").style.display = "inline-block";
    document.getElementById("brandRemoveLogoFlag").value = "";
    document.getElementById("brandLogoInput").value = "";
    const preview = document.getElementById("brandLogoPreview");
    if (b.logo_image) {
      preview.src = b.logo_image;
      preview.style.display = "block";
      document.getElementById("brandLogoPlaceholder").style.display = "none";
      document.getElementById("brandLogoRemoveBtn").style.display = "inline-flex";
    } else {
      preview.style.display = "none";
      document.getElementById("brandLogoPlaceholder").style.display = "";
      document.getElementById("brandLogoRemoveBtn").style.display = "none";
    }
  }

  function resetBrandForm() {
    const form = document.getElementById("addBrandForm");
    if (!form) return;
    form.reset();
    document.getElementById("brandEditingId").value = "";
    document.getElementById("brandSubmitBtn").textContent = "افزودن";
    document.getElementById("brandCancelEditBtn").style.display = "none";
    document.getElementById("brandRemoveLogoFlag").value = "";
    document.getElementById("brandLogoPreview").style.display = "none";
    document.getElementById("brandLogoPreview").src = "";
    document.getElementById("brandLogoPlaceholder").style.display = "";
    document.getElementById("brandLogoRemoveBtn").style.display = "none";
  }

  async function deleteBrand(id) {
    if (!confirm("حذف این برند، آن را از پروژه‌های مرتبط نیز حذف می‌کند. ادامه می‌دهید؟")) return;
    try {
      const data = await apiPostJson("api/brands_delete.php", { id });
      if (!data.ok) throw new Error(data.message);
      showToast("برند حذف شد.");
      await loadProjectBrands();
    } catch (err) {
      showToast(err.message || "خطا در حذف برند.", true);
    }
  }

  function wireProjectBrandModal() {
    const openBtns = document.querySelectorAll("#manageBrandsBtn, #pEditorManageBrandsBtn");
    const modal = document.getElementById("brandModal");
    const closeBtn = document.getElementById("brandModalCloseBtn");
    openBtns.forEach((openBtn) => openBtn.addEventListener("click", () => modal.classList.add("is-open")));
    if (closeBtn) closeBtn.addEventListener("click", () => { modal.classList.remove("is-open"); resetBrandForm(); });
    if (modal) {
      modal.addEventListener("click", (e) => {
        if (e.target === modal) { modal.classList.remove("is-open"); resetBrandForm(); }
      });
    }
    document.getElementById("brandCancelEditBtn")?.addEventListener("click", resetBrandForm);

    const form = document.getElementById("addBrandForm");
    const msgEl = document.getElementById("addBrandMsg");
    form?.addEventListener("submit", async (e) => {
      e.preventDefault();
      msgEl.textContent = "";
      msgEl.className = "form-msg";
      const editingId = document.getElementById("brandEditingId").value;

      const fd = new FormData(form);
      if (editingId) fd.append("id", editingId);

      try {
        const url = editingId ? "api/brands_edit.php" : "api/brands_add.php";
        const data = await apiPostForm(url, fd);
        if (!data.ok) throw new Error(data.message);
        msgEl.textContent = data.message;
        msgEl.classList.add("is-success");
        resetBrandForm();
        await loadProjectBrands();
      } catch (err) {
        msgEl.textContent = err.message || "خطا در ذخیره‌سازی برند.";
        msgEl.classList.add("is-error");
      }
    });

    document.getElementById("brandLogoInput")?.addEventListener("change", (e) => {
      const file = e.target.files?.[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = () => {
        const preview = document.getElementById("brandLogoPreview");
        preview.src = reader.result;
        preview.style.display = "block";
        document.getElementById("brandLogoPlaceholder").style.display = "none";
        document.getElementById("brandRemoveLogoFlag").value = "";
        document.getElementById("brandLogoRemoveBtn").style.display = "inline-flex";
      };
      reader.readAsDataURL(file);
    });
    document.getElementById("brandLogoRemoveBtn")?.addEventListener("click", () => {
      document.getElementById("brandLogoInput").value = "";
      document.getElementById("brandLogoPreview").style.display = "none";
      document.getElementById("brandLogoPreview").src = "";
      document.getElementById("brandLogoPlaceholder").style.display = "";
      document.getElementById("brandRemoveLogoFlag").value = "1";
      document.getElementById("brandLogoRemoveBtn").style.display = "none";
    });
  }

  /* ---------------------------------------------------------
     ادمین‌ها — انتخاب تیم پروژه
  --------------------------------------------------------- */
  async function loadAdminsForTeamPicker() {
    try {
      const data = await apiGet("api/admins_list.php");
      if (!data.ok) throw new Error(data.message);
      adminsCache = data.data.admins;
    } catch (err) {
      adminsCache = [];
    }
    renderAdminPicker();
  }

  function renderAdminPicker() {
    const wrap = document.getElementById("adminPickerList");
    if (!wrap) return;
    if (!adminsCache.length) {
      wrap.innerHTML = '<div class="empty-state">ادمینی یافت نشد.</div>';
      return;
    }
    wrap.innerHTML = adminsCache
      .map((a) => {
        const complete = a.has_display_name !== false;
        const isSelected = selectedAdminIds.has(a.id);
        const avatarHtml = a.avatar
          ? `<img src="../${a.avatar}" alt="">`
          : `<span>${escapeHtml(initialsOf(a.display_name))}</span>`;
        const lockedNote = !complete
          ? '<small class="admin-picker-warning">پروفایل تکمیل نشده — این ادمین باید ابتدا از بخش «تنظیمات» نام نمایشی خود را ثبت کند.</small>'
          : "";
        return `
        <label class="admin-picker-item ${isSelected ? "is-checked" : ""} ${!complete ? "is-disabled" : ""}" data-id="${a.id}">
          <input type="checkbox" ${isSelected ? "checked" : ""} ${!complete ? "disabled" : ""}>
          <span class="admin-picker-avatar">${avatarHtml}</span>
          <span class="admin-picker-info">
            <strong>${escapeHtml(a.display_name)}</strong>
            ${lockedNote}
          </span>
        </label>`;
      })
      .join("");

    wrap.querySelectorAll(".admin-picker-item:not(.is-disabled)").forEach((item) => {
      item.addEventListener("click", (e) => {
        e.preventDefault();
        const id = parseInt(item.dataset.id, 10);
        if (selectedAdminIds.has(id)) {
          if (!canSeeAllProjects && id === currentAdminId) {
            showToast("شما همیشه جزو تیم پروژه‌های خودتان هستید.", true);
            return;
          }
          selectedAdminIds.delete(id);
        } else {
          selectedAdminIds.add(id);
        }
        renderAdminPicker();
      });
    });
  }

  /* ---------------------------------------------------------
     اعضای دستی تیم (بدون حساب ادمین)
  --------------------------------------------------------- */
  function wireManualMembers() {
    document.getElementById("addManualMemberBtn")?.addEventListener("click", () => {
      manualMembers.push({ clientId: "m" + ++memberSeq, id: null, name: "", role_title: "", avatarFile: null, existingAvatar: null, removeAvatar: false, previewUrl: null });
      renderManualMembers();
    });
  }

  function renderManualMembers() {
    const wrap = document.getElementById("manualMembersList");
    if (!wrap) return;
    if (!manualMembers.length) {
      wrap.innerHTML = '<span class="empty-hint">هنوز عضوی اضافه نشده است.</span>';
      return;
    }
    wrap.innerHTML = manualMembers
      .map((m, i) => {
        const preview = m.previewUrl || (m.existingAvatar && !m.removeAvatar ? "../" + m.existingAvatar : "");
        const avatarInner = preview ? `<img src="${preview}" alt="">` : `<span>${escapeHtml(initialsOf(m.name))}</span>`;
        return `
        <div class="manual-member-row" data-cid="${m.clientId}">
          <div class="manual-member-avatar" data-action="pick-avatar" data-cid="${m.clientId}" title="انتخاب عکس">${avatarInner}</div>
          <div class="manual-member-fields">
            <input type="text" placeholder="نام" maxlength="150" value="${escapeHtml(m.name)}" data-field="name" data-cid="${m.clientId}">
            <input type="text" placeholder="سمت در پروژه (مثلاً طراح، برنامه‌نویس)" maxlength="150" value="${escapeHtml(m.role_title)}" data-field="role_title" data-cid="${m.clientId}">
          </div>
          <button type="button" class="table-del-btn" data-action="remove-member" data-cid="${m.clientId}" title="حذف">&times;</button>
          <input type="file" accept="image/*" style="display:none;" data-avatar-input="${m.clientId}">
        </div>`;
      })
      .join("");

    wrap.querySelectorAll("[data-field]").forEach((inp) => {
      inp.addEventListener("input", () => {
        const m = manualMembers.find((x) => x.clientId === inp.dataset.cid);
        if (m) m[inp.dataset.field] = inp.value;
      });
    });
    wrap.querySelectorAll("[data-action='remove-member']").forEach((btn) => {
      btn.addEventListener("click", () => {
        manualMembers = manualMembers.filter((x) => x.clientId !== btn.dataset.cid);
        renderManualMembers();
      });
    });
    wrap.querySelectorAll("[data-action='pick-avatar']").forEach((el) => {
      el.addEventListener("click", () => {
        wrap.querySelector(`[data-avatar-input="${el.dataset.cid}"]`)?.click();
      });
    });
    wrap.querySelectorAll("[data-avatar-input]").forEach((input) => {
      input.addEventListener("change", () => {
        const file = input.files[0];
        if (!file) return;
        if (file.size > 6 * 1024 * 1024) {
          showToast("حجم عکس نباید بیشتر از ۶ مگابایت باشد.", true);
          return;
        }
        const m = manualMembers.find((x) => x.clientId === input.dataset.avatarInput);
        if (!m) return;
        m.avatarFile = file;
        m.removeAvatar = false;
        const reader = new FileReader();
        reader.onload = (e) => {
          m.previewUrl = e.target.result;
          renderManualMembers();
        };
        reader.readAsDataURL(file);
      });
    });
  }

  /* ---------------------------------------------------------
     لیست پروژه‌ها (کارت‌های ادمین)
  --------------------------------------------------------- */
  function wireProjectFilters() {
    const search = document.getElementById("projectSearchInput");
    const catFilter = document.getElementById("projectCategoryFilter");
    const statusFilter = document.getElementById("projectStatusFilter");
    let debounce;
    search?.addEventListener("input", () => {
      clearTimeout(debounce);
      debounce = setTimeout(loadProjects, 350);
    });
    catFilter?.addEventListener("change", loadProjects);
    statusFilter?.addEventListener("change", loadProjects);
    document.getElementById("newProjectBtn")?.addEventListener("click", () => openProjectEditor(null));
  }

  async function loadProjects() {
    const grid = document.getElementById("projectsGridAdmin");
    const q = document.getElementById("projectSearchInput")?.value.trim() || "";
    const categoryId = document.getElementById("projectCategoryFilter")?.value || "";
    const status = document.getElementById("projectStatusFilter")?.value || "";

    const params = new URLSearchParams();
    if (q) params.set("q", q);
    if (categoryId) params.set("category_id", categoryId);
    if (status) params.set("status", status);

    try {
      const data = await apiGet("api/projects_list.php?" + params.toString());
      if (!data.ok) throw new Error(data.message);
      projectsCache = data.data.projects;
      renderProjectsGrid(projectsCache);
      const toFa = (n) => n.toLocaleString("fa-IR");
      document.getElementById("projTotalCount").textContent = toFa(data.data.total_count);
      document.getElementById("projPublishedCount").textContent = toFa(data.data.published_count);
      document.getElementById("projDraftCount").textContent = toFa(data.data.draft_count);
    } catch (err) {
      if (grid) grid.innerHTML = '<div class="empty-state">خطا در بارگذاری پروژه‌ها.</div>';
    }
  }

  function renderProjectsGrid(list) {
    const grid = document.getElementById("projectsGridAdmin");
    if (!grid) return;
    if (!list.length) {
      grid.innerHTML = '<div class="empty-state">هنوز پروژه‌ای ثبت نشده است. اولین پروژه را بسازید!</div>';
      return;
    }
    grid.innerHTML = list
      .map((p) => {
        const thumb =
          p.cover_type === "image" && p.cover_image
            ? `<img src="../${p.cover_image}" alt="">`
            : p.cover_type === "video" && p.cover_video
            ? `<div class="proj-thumb-video">🎬</div>`
            : `<div class="proj-thumb-empty">—</div>`;
        const statusBadge =
          p.status === "published"
            ? '<span class="status-badge status-badge--published">منتشر شده</span>'
            : '<span class="status-badge status-badge--draft">پیش‌نویس</span>';
        const cats = p.categories || [];
        const cat = cats.length
          ? cats.map((c) => `<span class="cat-badge">${escapeHtml(c.name)}</span>`).join(" ")
          : `<span class="cat-badge cat-badge--empty">بدون دسته</span>`;
        const teamHtml = (p.team || [])
          .slice(0, 5)
          .map((t) =>
            t.avatar
              ? `<span class="team-avatar-stack-item"><img src="../${t.avatar}" alt="" title="${escapeHtml(t.name)}"></span>`
              : `<span class="team-avatar-stack-item" title="${escapeHtml(t.name)}">${escapeHtml(initialsOf(t.name))}</span>`
          )
          .join("");
        const previewBtn =
          p.status === "published"
            ? `<a class="icon-btn" target="_blank" href="../project.html?slug=${encodeURIComponent(p.slug)}" title="مشاهده در سایت"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12s3.5-7 9-7 9 7 9 7-3.5 7-9 7-9-7-9-7Z"/><circle cx="12" cy="12" r="3"/></svg></a>`
            : "";
        return `
        <div class="proj-card-admin" data-id="${p.id}">
          <div class="proj-card-admin-thumb">${thumb}</div>
          <div class="proj-card-admin-body">
            <div class="proj-card-admin-title"><a href="#" data-action="edit" data-id="${p.id}">${escapeHtml(p.title)}</a></div>
            <div class="proj-card-admin-meta">${cat} ${statusBadge}</div>
            <div class="proj-card-admin-team">${teamHtml}</div>
            <div class="proj-card-admin-date">${formatDate(p.published_at || p.created_at)}</div>
          </div>
          <div class="proj-card-admin-actions">
            ${previewBtn}
            <button class="icon-btn" data-action="edit" data-id="${p.id}" title="ویرایش">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L18.5 9.5a2 2 0 0 0-4-4L4 16v4Z"/><path d="M13 6.5 17.5 11"/></svg>
            </button>
            <button class="icon-btn icon-btn--danger" data-action="delete" data-id="${p.id}" title="حذف">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M9 7V4.5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1V7"/><path d="M6 7l1 13a1.5 1.5 0 0 0 1.5 1.4h7a1.5 1.5 0 0 0 1.5-1.4L18 7"/></svg>
            </button>
          </div>
        </div>`;
      })
      .join("");

    grid.querySelectorAll('[data-action="edit"]').forEach((el) => {
      el.addEventListener("click", (e) => {
        e.preventDefault();
        openProjectEditor(parseInt(el.dataset.id, 10));
      });
    });
    grid.querySelectorAll('[data-action="delete"]').forEach((el) => {
      el.addEventListener("click", () => deleteProject(parseInt(el.dataset.id, 10)));
    });
  }

  async function deleteProject(id) {
    if (!confirm("آیا از حذف این پروژه مطمئن هستید؟ این عملیات قابل بازگشت نیست.")) return;
    try {
      const data = await apiPostJson("api/projects_delete.php", { id });
      if (!data.ok) throw new Error(data.message);
      showToast("پروژه حذف شد.");
      await loadProjects();
    } catch (err) {
      showToast(err.message || "خطا در حذف پروژه.", true);
    }
  }

  /* ---------------------------------------------------------
     کشوی ویرایشگر پروژه
  --------------------------------------------------------- */
  function wireProjectEditorShell() {
    document.getElementById("pEditorCloseBtn")?.addEventListener("click", closeProjectEditor);
    document.getElementById("pEditorCancelBtn")?.addEventListener("click", closeProjectEditor);
    document.getElementById("pEditorPreviewBtn")?.addEventListener("click", openProjectPreview);
    document.getElementById("pEditorPublishBtn")?.addEventListener("click", () => saveProject("published"));
    document.getElementById("pEditorSaveDraftBtn")?.addEventListener("click", () => saveProject("draft"));
    document.getElementById("pFieldDisplayScope")?.addEventListener("change", updateBrandsCardVisibility);
  }

  function closeProjectEditor() {
    document.getElementById("projectEditorBackdrop").classList.remove("is-open");
  }

  function openProjectEditor(id) {
    resetProjectEditorForm();
    document.getElementById("projectEditorBackdrop").classList.add("is-open");
    currentEditId = id;

    if (id) {
      document.getElementById("pEditorTitle").textContent = "در حال بارگذاری…";
      apiGet("api/projects_get.php?id=" + id)
        .then((data) => {
          if (!data.ok) throw new Error(data.message);
          fillProjectEditorForm(data.data.project);
        })
        .catch((err) => {
          showToast(err.message || "خطا در بارگذاری پروژه.", true);
          closeProjectEditor();
        });
    } else {
      document.getElementById("pEditorTitle").textContent = "پروژه جدید";
      setProjectStatusButtonLabels("draft");
      if (!canSeeAllProjects) selectedAdminIds.add(currentAdminId);
      renderAdminPicker();
    }
  }

  function setProjectStatusButtonLabels(status) {
    const draftBtn = document.getElementById("pEditorSaveDraftBtn");
    const pubBtn = document.getElementById("pEditorPublishBtn");
    if (status === "published") {
      draftBtn.textContent = "تبدیل به پیش‌نویس";
      pubBtn.textContent = "بروزرسانی پروژه";
    } else {
      draftBtn.textContent = "ذخیره پیش‌نویس";
      pubBtn.textContent = "انتشار پروژه";
    }
  }

  function resetProjectEditorForm() {
    currentEditId = null;
    slugManuallyEdited = false;
    isHtmlSourceMode = false;
    coverType = "image";
    currentCoverImageFile = null;
    removeCoverFlag = false;
    existingCoverImage = null;
    currentCoverVideoFile = null;
    existingCoverVideo = null;
    selectedAdminIds = new Set();
    manualMembers = [];

    document.getElementById("pFieldTitle").value = "";
    document.getElementById("pFieldSlug").value = "";
    document.getElementById("pFieldExcerpt").value = "";
    document.getElementById("pRteEditable").innerHTML = "";
    document.getElementById("pRteHtmlView").value = "";
    document.getElementById("pRteHtmlView").style.display = "none";
    document.getElementById("pRteEditable").style.display = "block";
    renderCategoryChips([]);
    renderBrandChips([]);
    document.getElementById("pFieldPublishedAt").value = "";
    document.getElementById("pFieldMetaTitle").value = "";
    document.getElementById("pFieldMetaDescription").value = "";
    document.getElementById("pFieldDisplayScope").value = "both";
    updateBrandsCardVisibility();

    document.getElementById("pCoverPreview").style.display = "none";
    document.getElementById("pCoverPreview").src = "";
    document.getElementById("pCoverPlaceholder").style.display = "block";
    document.getElementById("pRemoveCoverBtn").style.display = "none";

    document.getElementById("pCoverVideoPreview").style.display = "none";
    document.getElementById("pCoverVideoPreview").src = "";
    document.getElementById("pCoverVideoPlaceholder").style.display = "block";
    document.getElementById("pCoverVideoUrlInput").value = "";
    document.getElementById("pRemoveCoverVideoBtn").style.display = "none";

    setCoverTypeUI("image");
    document.getElementById("projectFormMsg").textContent = "";
    setProjectStatusButtonLabels("draft");
    renderAdminPicker();
    renderManualMembers();
  }

  function fillProjectEditorForm(p) {
    document.getElementById("pEditorTitle").textContent = "ویرایش: " + p.title;
    document.getElementById("pFieldTitle").value = p.title || "";
    document.getElementById("pFieldSlug").value = p.slug || "";
    slugManuallyEdited = true;
    document.getElementById("pFieldExcerpt").value = p.excerpt || "";
    document.getElementById("pRteEditable").innerHTML = prepareProjectContentForEditing(p.content || "");
    renderCategoryChips(p.category_ids && p.category_ids.length ? p.category_ids : (p.category_id ? [p.category_id] : []));
    renderBrandChips(p.brand_ids || []);
    document.getElementById("pFieldPublishedAt").value = toLocalDatetimeValue(p.published_at);
    document.getElementById("pFieldMetaTitle").value = p.meta_title || "";
    document.getElementById("pFieldMetaDescription").value = p.meta_description || "";
    document.getElementById("pFieldDisplayScope").value = p.display_scope || "both";
    updateBrandsCardVisibility();

    coverType = p.cover_type === "video" ? "video" : "image";
    setCoverTypeUI(coverType);

    existingCoverImage = p.cover_image || null;
    if (existingCoverImage) {
      const img = document.getElementById("pCoverPreview");
      img.src = "../" + existingCoverImage;
      img.style.display = "block";
      document.getElementById("pCoverPlaceholder").style.display = "none";
      document.getElementById("pRemoveCoverBtn").style.display = "inline-block";
    }

    existingCoverVideo = p.cover_video || null;
    if (existingCoverVideo) {
      const vid = document.getElementById("pCoverVideoPreview");
      const isUpload = existingCoverVideo.startsWith("assets/uploads/");
      if (isUpload) {
        vid.src = "../" + existingCoverVideo;
        vid.style.display = "block";
        document.getElementById("pCoverVideoPlaceholder").style.display = "none";
      } else {
        document.getElementById("pCoverVideoUrlInput").value = existingCoverVideo;
      }
      document.getElementById("pRemoveCoverVideoBtn").style.display = "inline-block";
    }

    selectedAdminIds = new Set(p.admin_ids || []);
    manualMembers = (p.members || []).map((m) => ({
      clientId: "m" + ++memberSeq,
      id: m.id,
      name: m.name || "",
      role_title: m.role_title || "",
      avatarFile: null,
      existingAvatar: m.avatar || null,
      removeAvatar: false,
      previewUrl: null,
    }));

    setProjectStatusButtonLabels(p.status);
    renderAdminPicker();
    renderManualMembers();
  }

  function toLocalDatetimeValue(mysqlDateTime) {
    if (!mysqlDateTime) return "";
    return mysqlDateTime.replace(" ", "T").slice(0, 16);
  }

  /* ---------------------------------------------------------
     اسلاگ خودکار
  --------------------------------------------------------- */
  function wireProjectSlugTracking() {
    document.getElementById("pFieldSlug")?.addEventListener("input", () => { slugManuallyEdited = true; });
  }

  /* ---------------------------------------------------------
     رسانه شاخص: عکس / ویدیو
  --------------------------------------------------------- */
  function setCoverTypeUI(type) {
    coverType = type;
    document.querySelectorAll("#coverTypeToggle .cover-type-btn").forEach((btn) => {
      btn.classList.toggle("is-active", btn.dataset.type === type);
    });
    document.getElementById("pCoverImageWrap").style.display = type === "image" ? "block" : "none";
    document.getElementById("pCoverVideoWrap").style.display = type === "video" ? "block" : "none";
  }

  function wireProjectCoverUpload() {
    document.querySelectorAll("#coverTypeToggle .cover-type-btn").forEach((btn) => {
      btn.addEventListener("click", () => setCoverTypeUI(btn.dataset.type));
    });

    const drop = document.getElementById("pCoverDrop");
    const fileInput = document.getElementById("pCoverFileInput");
    drop?.addEventListener("click", () => fileInput.click());
    fileInput?.addEventListener("change", () => {
      const file = fileInput.files[0];
      if (!file) return;
      if (file.size > 6 * 1024 * 1024) { showToast("حجم تصویر نباید بیشتر از ۶ مگابایت باشد.", true); return; }
      currentCoverImageFile = file;
      removeCoverFlag = false;
      const reader = new FileReader();
      reader.onload = (e) => {
        const img = document.getElementById("pCoverPreview");
        img.src = e.target.result;
        img.style.display = "block";
        document.getElementById("pCoverPlaceholder").style.display = "none";
        document.getElementById("pRemoveCoverBtn").style.display = "inline-block";
      };
      reader.readAsDataURL(file);
    });
    document.getElementById("pRemoveCoverBtn")?.addEventListener("click", (e) => {
      e.stopPropagation();
      currentCoverImageFile = null;
      removeCoverFlag = true;
      fileInput.value = "";
      document.getElementById("pCoverPreview").style.display = "none";
      document.getElementById("pCoverPlaceholder").style.display = "block";
      document.getElementById("pRemoveCoverBtn").style.display = "none";
    });

    const vDrop = document.getElementById("pCoverVideoDrop");
    const vFileInput = document.getElementById("pCoverVideoFileInput");
    vDrop?.addEventListener("click", () => vFileInput.click());
    vFileInput?.addEventListener("change", () => {
      const file = vFileInput.files[0];
      if (!file) return;
      if (file.size > 60 * 1024 * 1024) { showToast("حجم ویدیو نباید بیشتر از ۶۰ مگابایت باشد.", true); return; }
      currentCoverVideoFile = file;
      const vid = document.getElementById("pCoverVideoPreview");
      vid.src = URL.createObjectURL(file);
      vid.style.display = "block";
      document.getElementById("pCoverVideoPlaceholder").style.display = "none";
      document.getElementById("pRemoveCoverVideoBtn").style.display = "inline-block";
      document.getElementById("pCoverVideoUrlInput").value = "";
    });
    document.getElementById("pRemoveCoverVideoBtn")?.addEventListener("click", (e) => {
      e.stopPropagation();
      currentCoverVideoFile = null;
      existingCoverVideo = null;
      vFileInput.value = "";
      document.getElementById("pCoverVideoPreview").style.display = "none";
      document.getElementById("pCoverVideoPreview").src = "";
      document.getElementById("pCoverVideoPlaceholder").style.display = "block";
      document.getElementById("pCoverVideoUrlInput").value = "";
      document.getElementById("pRemoveCoverVideoBtn").style.display = "none";
    });
  }

  /* ---------------------------------------------------------
     ویرایشگر متنی غنی (RTE) — با افزودن ویدیو و آهنگ
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

  function wireProjectToolbar() {
    const toolbar = document.getElementById("pRteToolbar");
    const editable = document.getElementById("pRteEditable");
    if (!toolbar || !editable) return;

    toolbar.querySelectorAll("button[data-cmd]").forEach((btn) => {
      btn.addEventListener("click", () => {
        editable.focus();
        const cmd = btn.dataset.cmd;
        if (cmd === "blockquote") document.execCommand("formatBlock", false, "blockquote");
        else document.execCommand(cmd, false, null);
      });
    });
    toolbar.querySelectorAll("button[data-block]").forEach((btn) => {
      btn.addEventListener("click", () => {
        editable.focus();
        document.execCommand("formatBlock", false, btn.dataset.block);
      });
    });
    document.getElementById("pRteTextColor")?.addEventListener("input", (e) => {
      editable.focus();
      document.execCommand("foreColor", false, e.target.value);
    });
    document.getElementById("pRteHiliteColor")?.addEventListener("input", (e) => {
      editable.focus();
      document.execCommand("hiliteColor", false, e.target.value);
    });
    document.getElementById("pRteLinkBtn")?.addEventListener("click", () => {
      insertRteLink(editable);
    });

    const imageFileInput = document.getElementById("pRteImageFile");
    document.getElementById("pRteImageBtn")?.addEventListener("click", () => imageFileInput.click());
    imageFileInput?.addEventListener("change", async () => {
      const file = imageFileInput.files[0];
      if (!file) return;
      if (file.size > 6 * 1024 * 1024) { showToast("حجم تصویر نباید بیشتر از ۶ مگابایت باشد.", true); return; }
      const fd = new FormData();
      fd.append("image", file);
      showToast("در حال آپلود تصویر...");
      try {
        const data = await apiPostForm("api/upload_image.php", fd);
        if (!data.ok) throw new Error(data.message);
        editable.focus();
        document.execCommand("insertHTML", false, `<img src="../${data.data.url}" data-src="${data.data.url}" alt="">`);
      } catch (err) {
        showToast(err.message || "خطا در آپلود تصویر.", true);
      }
      imageFileInput.value = "";
    });

    const videoFileInput = document.getElementById("pRteVideoFile");
    document.getElementById("pRteVideoBtn")?.addEventListener("click", () => {
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
      if (file.size > 60 * 1024 * 1024) { showToast("حجم ویدیو نباید بیشتر از ۶۰ مگابایت باشد.", true); return; }
      const fd = new FormData();
      fd.append("media", file);
      fd.append("kind", "video");
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

    const audioFileInput = document.getElementById("pRteAudioFile");
    document.getElementById("pRteAudioBtn")?.addEventListener("click", () => audioFileInput.click());
    audioFileInput?.addEventListener("change", async () => {
      const file = audioFileInput.files[0];
      if (!file) return;
      if (file.size > 25 * 1024 * 1024) { showToast("حجم فایل صوتی نباید بیشتر از ۲۵ مگابایت باشد.", true); return; }
      const fd = new FormData();
      fd.append("media", file);
      fd.append("kind", "audio");
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

    document.getElementById("pRteHtmlToggleBtn")?.addEventListener("click", toggleProjectHtmlSourceMode);

    document.getElementById("pRteHrBtn")?.addEventListener("click", () => {
      editable.focus();
      document.execCommand("insertHTML", false, "<hr>");
    });

    document.getElementById("pRteCodeBtn")?.addEventListener("click", () => {
      editable.focus();
      document.execCommand("formatBlock", false, "PRE");
    });

    document.getElementById("pRteTableBtn")?.addEventListener("click", () => {
      const rowsInput = prompt("تعداد ردیف‌های جدول (بدون سرستون):", "3");
      if (rowsInput === null) return;
      const colsInput = prompt("تعداد ستون‌های جدول:", "3");
      if (colsInput === null) return;
      const rows = Math.min(Math.max(parseInt(rowsInput, 10) || 0, 1), 30);
      const cols = Math.min(Math.max(parseInt(colsInput, 10) || 0, 1), 12);
      editable.focus();
      document.execCommand("insertHTML", false, buildProjectTableHtml(rows, cols));
    });

    wireProjectEmojiPanel(document.getElementById("pRteEmojiBtn"), document.getElementById("pRteEmojiPanel"), editable);
  }

  /**
   * ساخت جدول HTML قابل‌ویرایش با یک ردیف سرستون.
   */
  function buildProjectTableHtml(rows, cols) {
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
  function wireProjectEmojiPanel(btn, panel, editable) {
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

  function toggleProjectHtmlSourceMode() {
    const editable = document.getElementById("pRteEditable");
    const htmlView = document.getElementById("pRteHtmlView");
    if (!isHtmlSourceMode) {
      htmlView.value = prepareProjectContentForSaving(editable.innerHTML);
      editable.style.display = "none";
      htmlView.style.display = "block";
    } else {
      editable.innerHTML = prepareProjectContentForEditing(htmlView.value);
      htmlView.style.display = "none";
      editable.style.display = "block";
    }
    isHtmlSourceMode = !isHtmlSourceMode;
  }

  function prepareProjectContentForEditing(html) {
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

  function prepareProjectContentForSaving(html) {
    const div = document.createElement("div");
    div.innerHTML = html;
    div.querySelectorAll("[data-src]").forEach((el) => {
      el.setAttribute("src", el.getAttribute("data-src"));
      el.removeAttribute("data-src");
    });
    return div.innerHTML;
  }

  function getFinalProjectContentHtml() {
    if (isHtmlSourceMode) return prepareProjectContentForSaving(document.getElementById("pRteHtmlView").value);
    return prepareProjectContentForSaving(document.getElementById("pRteEditable").innerHTML);
  }

  /* ---------------------------------------------------------
     ذخیره پروژه
  --------------------------------------------------------- */
  /**
   * محتوای فعلی فرم پروژه (چه ذخیره شده باشد چه نه) را به preview_save.php
   * می‌فرستد و صفحه‌ی واقعی پروژه را در یک تب جدید، در «حالت پیش‌نمایش» باز می‌کند.
   */
  async function openProjectPreview() {
    const title = document.getElementById("pFieldTitle").value.trim();
    if (title.length < 3) {
      showToast("برای پیش‌نمایش، عنوان پروژه را وارد کنید.", true);
      return;
    }

    const firstCatId = selectedCategoryIds.size ? Array.from(selectedCategoryIds)[0] : null;
    const cat = firstCatId ? categoriesCache.find((c) => String(c.id) === String(firstCatId)) : null;

    const payload = {
      type: "project",
      title,
      slug: document.getElementById("pFieldSlug").value.trim(),
      excerpt: document.getElementById("pFieldExcerpt").value.trim(),
      content: getFinalProjectContentHtml(),
      category_name: cat?.name || "",
      category_slug: cat?.slug || "",
      published_at: document.getElementById("pFieldPublishedAt").value,
      meta_title: document.getElementById("pFieldMetaTitle").value.trim(),
      meta_description: document.getElementById("pFieldMetaDescription").value.trim(),
      cover_type: coverType,
    };

    if (coverType === "image") {
      const img = document.getElementById("pCoverPreview");
      payload.cover_image = img && img.style.display !== "none" ? img.src : "";
    } else {
      const urlVal = document.getElementById("pCoverVideoUrlInput").value.trim();
      payload.cover_video = urlVal || existingCoverVideo || "";
    }

    try {
      const data = await apiPostJson("api/preview_save.php", payload);
      if (!data.ok) throw new Error(data.message);
      window.open("../project.html?preview_token=" + encodeURIComponent(data.data.token), "_blank");
    } catch (err) {
      showToast(err.message || "خطا در ساخت پیش‌نمایش.", true);
    }
  }

  async function saveProject(status) {
    const title = document.getElementById("pFieldTitle").value.trim();
    if (title.length < 3) { showToast("عنوان پروژه باید حداقل ۳ کاراکتر باشد.", true); return; }

    const incompleteSelected = adminsCache.find((a) => selectedAdminIds.has(a.id) && a.has_display_name === false);
    if (incompleteSelected) {
      showToast("یکی از ادمین‌های انتخاب‌شده پروفایل خود را تکمیل نکرده است.", true);
      return;
    }

    const msgEl = document.getElementById("projectFormMsg");
    msgEl.textContent = "در حال ذخیره...";

    const fd = new FormData();
    if (currentEditId) fd.append("id", currentEditId);
    fd.append("title", title);
    fd.append("slug", document.getElementById("pFieldSlug").value.trim());
    fd.append("excerpt", document.getElementById("pFieldExcerpt").value.trim());
    fd.append("content", getFinalProjectContentHtml());
    fd.append("category_ids", JSON.stringify(Array.from(selectedCategoryIds)));
    fd.append("brand_ids", JSON.stringify(Array.from(selectedBrandIds)));
    fd.append("status", status);
    fd.append("published_at", document.getElementById("pFieldPublishedAt").value);
    fd.append("meta_title", document.getElementById("pFieldMetaTitle").value.trim());
    fd.append("meta_description", document.getElementById("pFieldMetaDescription").value.trim());
    fd.append("display_scope", document.getElementById("pFieldDisplayScope").value);

    fd.append("cover_type", coverType);
    if (coverType === "image") {
      if (currentCoverImageFile) fd.append("cover_image", currentCoverImageFile);
      if (removeCoverFlag) fd.append("remove_cover", "1");
    } else {
      if (currentCoverVideoFile) fd.append("cover_video_file", currentCoverVideoFile);
      const urlVal = document.getElementById("pCoverVideoUrlInput").value.trim();
      if (urlVal) fd.append("cover_video_url", urlVal);
      if (!currentCoverVideoFile && !urlVal && !existingCoverVideo) fd.append("remove_cover", "1");
    }

    fd.append("admin_ids", JSON.stringify(Array.from(selectedAdminIds)));
    const membersPayload = manualMembers
      .filter((m) => m.name.trim())
      .map((m) => ({ id: m.id, name: m.name.trim(), role_title: m.role_title.trim(), remove_avatar: m.removeAvatar }));
    fd.append("members", JSON.stringify(membersPayload));
    manualMembers
      .filter((m) => m.name.trim())
      .forEach((m, i) => {
        if (m.avatarFile) fd.append("member_avatar_" + i, m.avatarFile);
      });

    try {
      const data = await apiPostForm("api/projects_save.php", fd);
      if (!data.ok) throw new Error(data.message);
      msgEl.textContent = "";
      showToast(data.message || "پروژه ذخیره شد.");
      closeProjectEditor();
      await loadProjects();
      await loadProjectCategories();
    } catch (err) {
      msgEl.textContent = "";
      showToast(err.message || "خطا در ذخیره‌سازی پروژه.", true);
    }
  }
})();
