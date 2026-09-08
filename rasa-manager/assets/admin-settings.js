(() => {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || "";

  function escapeHtml(str) {
    const div = document.createElement("div");
    div.textContent = str ?? "";
    return div.innerHTML;
  }

  function formatDate(isoLike) {
    try {
      const d = new Date(String(isoLike).replace(" ", "T"));
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
    const res = await fetch(url, {
      method: body ? "POST" : "GET",
      headers: { "X-CSRF-Token": csrfToken, "Content-Type": "application/json" },
      body: body ? JSON.stringify(body) : undefined,
    });
    let data;
    try {
      data = await res.json();
    } catch (e) {
      throw new Error("پاسخ نامعتبر از سرور دریافت شد.");
    }
    if (!data.ok) {
      throw new Error(data.message || "خطایی رخ داد.");
    }
    return data;
  }

  async function apiPostForm(url, formData) {
    const res = await fetch(url, {
      method: "POST",
      headers: { "X-CSRF-Token": csrfToken },
      body: formData,
    });
    let data;
    try {
      data = await res.json();
    } catch (e) {
      throw new Error("پاسخ نامعتبر از سرور دریافت شد.");
    }
    if (!data.ok) {
      throw new Error(data.message || "خطایی رخ داد.");
    }
    return data;
  }

  const ACTION_LABELS = {
    login_success: "ورود موفق",
    login_failed: "تلاش ناموفق ورود",
    logout: "خروج از پنل",
    admin_add: "افزودن ادمین",
    admin_update: "ویرایش ادمین",
    admin_delete: "حذف ادمین",
    maintenance_on: "فعال‌سازی حالت تعمیرات",
    maintenance_off: "غیرفعال‌سازی حالت تعمیرات",
    settings_comments_auto_approve: "تغییر تنظیم نظرات",
    message_delete: "حذف پیام تماس",
    comment_status_change: "تغییر وضعیت نظر",
    comment_delete: "حذف نظر",
  };

  const ACTION_TONES = {
    login_failed: "log-tone-warn",
    admin_delete: "log-tone-warn",
    comment_delete: "log-tone-warn",
    message_delete: "log-tone-warn",
    maintenance_on: "log-tone-warn",
    maintenance_off: "log-tone-ok",
    login_success: "log-tone-ok",
  };

  /* ---------------- حالت تعمیرات سایت ---------------- */
  function initMaintenanceSettings() {
    const toggle = document.getElementById("maintenanceModeToggle");
    const messageInput = document.getElementById("maintenanceMessageInput");
    const saveBtn = document.getElementById("saveMaintenanceBtn");
    const msgBox = document.getElementById("maintenanceMsg");
    const statusNote = document.getElementById("maintenanceStatusNote");
    if (!toggle || !saveBtn) return;

    function renderStatus() {
      if (!statusNote) return;
      if (toggle.checked) {
        statusNote.style.display = "block";
        statusNote.className = "maintenance-status-note is-on";
        statusNote.textContent = "⚠ حالت تعمیرات هم‌اکنون فعال است — سایت برای بازدیدکنندگان در دسترس نیست.";
      } else {
        statusNote.style.display = "none";
      }
    }

    apiCall("api/settings_get.php")
      .then((data) => {
        toggle.checked = !!data.data.maintenance_mode;
        if (messageInput) messageInput.value = data.data.maintenance_message || "";
        renderStatus();
      })
      .catch(() => {});

    toggle.addEventListener("change", renderStatus);

    saveBtn.addEventListener("click", async () => {
      saveBtn.disabled = true;
      try {
        const data = await apiCall("api/settings_save.php", {
          maintenance_mode: toggle.checked,
          maintenance_message: messageInput ? messageInput.value.trim() : "",
        });
        if (msgBox) {
          msgBox.textContent = data.message || "تنظیمات ذخیره شد.";
          msgBox.classList.remove("is-error");
        }
        showToast(toggle.checked ? "حالت تعمیرات فعال شد." : "حالت تعمیرات غیرفعال شد.");
        renderStatus();
        loadActivityLog();
      } catch (e) {
        if (msgBox) {
          msgBox.textContent = e.message;
          msgBox.classList.add("is-error");
        }
        showToast(e.message, true);
      } finally {
        saveBtn.disabled = false;
      }
    });
  }

  /* ---------------- آمار بازدید سایت ---------------- */
  function renderVisitStats(stats) {
    const cardsWrap = document.getElementById("visitStatsCards");
    const chartWrap = document.getElementById("visitChart");
    const topPagesWrap = document.getElementById("topPagesList");

    if (cardsWrap) {
      cardsWrap.innerHTML = `
        <div class="stat-card">
          <div class="stat-card-num">${(stats.total || 0).toLocaleString("fa-IR")}</div>
          <div class="stat-card-label">کل بازدیدها</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-num">${(stats.today || 0).toLocaleString("fa-IR")}</div>
          <div class="stat-card-label">بازدید امروز</div>
        </div>
      `;
    }

    if (chartWrap) {
      const days = stats.last_7_days || [];
      const max = Math.max(1, ...days.map((d) => d.count));
      chartWrap.innerHTML =
        '<div class="visit-chart-title">بازدید ۷ روز اخیر</div><div class="visit-chart-bars">' +
        days
          .map((d) => {
            const h = Math.round((d.count / max) * 100);
            const label = new Date(d.date + "T00:00:00").toLocaleDateString("fa-IR", { weekday: "short", day: "numeric", month: "short" });
            return `
            <div class="visit-bar-col" title="${escapeHtml(label)}: ${d.count} بازدید">
              <div class="visit-bar" style="height:${Math.max(h, 3)}%"></div>
              <div class="visit-bar-count">${d.count}</div>
              <div class="visit-bar-label">${escapeHtml(label)}</div>
            </div>`;
          })
          .join("") +
        "</div>";
    }

    if (topPagesWrap) {
      const pages = stats.top_pages || [];
      if (!pages.length) {
        topPagesWrap.innerHTML = '<div class="empty-state">هنوز بازدیدی ثبت نشده است.</div>';
      } else {
        topPagesWrap.innerHTML =
          '<div class="visit-chart-title">پربازدیدترین صفحات</div><ul class="top-pages-list">' +
          pages.map((p) => `<li><span class="top-page-path">${escapeHtml(p.page_path)}</span><span class="top-page-count">${p.c}</span></li>`).join("") +
          "</ul>";
      }
    }
  }

  function initVisitStats() {
    const cardsWrap = document.getElementById("visitStatsCards");
    if (!cardsWrap) return;
    apiCall("api/visit_stats.php")
      .then((data) => renderVisitStats(data.data))
      .catch(() => {
        cardsWrap.innerHTML = '<div class="empty-state">دریافت آمار بازدید با خطا مواجه شد.</div>';
      });
  }

  /* ---------------- گزارش فعالیت‌ها ---------------- */
  function renderActivityLog(logs) {
    const wrap = document.getElementById("activityLogList");
    if (!wrap) return;
    if (!logs || !logs.length) {
      wrap.innerHTML = '<div class="empty-state">هنوز رویدادی ثبت نشده است.</div>';
      return;
    }
    wrap.innerHTML = logs
      .map((log) => {
        const label = ACTION_LABELS[log.action] || log.action;
        const tone = ACTION_TONES[log.action] || "";
        return `
        <div class="log-item ${tone}">
          <div class="log-item-top">
            <span class="log-action">${escapeHtml(label)}</span>
            <span class="log-date">${formatDate(log.created_at)}</span>
          </div>
          ${log.description ? `<div class="log-desc">${escapeHtml(log.description)}</div>` : ""}
          <div class="log-meta">
            ${log.admin_username ? `<span>کاربر: ${escapeHtml(log.admin_username)}</span>` : ""}
            ${log.ip_address ? `<span>IP: ${escapeHtml(log.ip_address)}</span>` : ""}
          </div>
        </div>`;
      })
      .join("");
  }

  function loadActivityLog() {
    const wrap = document.getElementById("activityLogList");
    if (!wrap) return;
    apiCall("api/activity_log_list.php")
      .then((data) => renderActivityLog(data.data.logs))
      .catch(() => {
        wrap.innerHTML = '<div class="empty-state">دریافت گزارش فعالیت‌ها با خطا مواجه شد.</div>';
      });
  }

  /* ---------------------------------------------------------
     پشتیبان‌گیری
  --------------------------------------------------------- */
  function formatBytes(bytes) {
    if (bytes === null || bytes === undefined) return "نامشخص";
    const units = ["بایت", "کیلوبایت", "مگابایت", "گیگابایت"];
    let v = bytes;
    let i = 0;
    while (v >= 1024 && i < units.length - 1) {
      v /= 1024;
      i++;
    }
    return v.toFixed(i === 0 ? 0 : 1).toLocaleString("fa-IR") + " " + units[i];
  }

  function healthCard(value, label) {
    return `<div class="stat-card"><div class="stat-card-num" style="font-size:1.1rem;">${escapeHtml(value)}</div><div class="stat-card-label">${label}</div></div>`;
  }

  function initSystemHealth() {
    const cardsWrap = document.getElementById("healthCards");
    const listWrap = document.getElementById("healthBackupsList");
    if (!cardsWrap) return;

    apiCall("api/system_health.php")
      .then((data) => {
        if (!data.ok) throw new Error(data.message);
        const h = data.data;
        cardsWrap.innerHTML =
          healthCard(h.db_connected ? "متصل ✅" : "قطع ❌", "وضعیت دیتابیس") +
          healthCard(formatBytes(h.db_size_bytes), "حجم دیتابیس") +
          healthCard(h.db_table_count.toLocaleString("fa-IR"), "تعداد جدول‌ها") +
          healthCard(formatBytes(h.disk_free_bytes), "فضای آزاد سرور") +
          healthCard("PHP " + h.php_version, "نسخه PHP") +
          healthCard(h.last_backup_at ? formatDate(h.last_backup_at) : "هنوز بک‌آپی نیست", "آخرین پشتیبان");

        if (listWrap) {
          if (!h.backups.length) {
            listWrap.innerHTML = '<div class="empty-state">هنوز فایل پشتیبانی روی سرور ذخیره نشده است.</div>';
          } else {
            listWrap.innerHTML = h.backups
              .map(
                (b) => `
                <div class="dash-top-item">
                  <span>${b.is_auto ? "🕒 خودکار" : "📥 دستی"} — ${escapeHtml(b.filename)}</span>
                  <span style="display:flex;align-items:center;gap:.6rem;">
                    <span class="dash-top-views">${formatBytes(b.size_bytes)} — ${formatDate(b.created_at)}</span>
                    <a class="btn-ghost" style="padding:.3rem .7rem;font-size:.75rem;" href="api/backup_download.php?file=${encodeURIComponent(b.filename)}">دانلود</a>
                  </span>
                </div>`
              )
              .join("");
          }
        }

        const scheduleSel = document.getElementById("backupScheduleSelect");
        if (scheduleSel) scheduleSel.value = h.backup_schedule || "off";
      })
      .catch(() => {
        cardsWrap.innerHTML = '<div class="empty-state">دریافت وضعیت سلامت سیستم با خطا مواجه شد.</div>';
      });
  }

  function initBackupSchedule() {
    const sel = document.getElementById("backupScheduleSelect");
    if (!sel) return;
    sel.addEventListener("change", async () => {
      const msg = document.getElementById("backupScheduleMsg");
      try {
        const data = await apiCall("api/settings_save.php", { backup_schedule: sel.value });
        if (!data.ok) throw new Error(data.message);
        if (msg) { msg.textContent = "ذخیره شد."; msg.classList.remove("is-error"); }
        showToast("زمان‌بندی پشتیبان‌گیری خودکار به‌روزرسانی شد.");
      } catch (err) {
        if (msg) { msg.textContent = err.message; msg.classList.add("is-error"); }
        showToast(err.message || "خطا در ذخیره‌سازی.", true);
      }
    });
  }

  function initBackup() {
    const scopeBtns = document.querySelectorAll(".backup-scope-btn");
    const sectionsGrid = document.getElementById("backupSectionsGrid");
    const downloadBtn = document.getElementById("downloadBackupBtn");
    if (!downloadBtn) return;

    let currentScope = "full";
    scopeBtns.forEach((btn) => {
      btn.addEventListener("click", () => {
        scopeBtns.forEach((b) => b.classList.remove("is-active"));
        btn.classList.add("is-active");
        currentScope = btn.dataset.scope;
        if (sectionsGrid) sectionsGrid.style.display = currentScope === "partial" ? "grid" : "none";
      });
    });

    downloadBtn.addEventListener("click", async () => {
      const msg = document.getElementById("backupMsg");
      let sections = [];
      if (currentScope === "partial") {
        sections = Array.from(document.querySelectorAll('#backupSectionsGrid input[type="checkbox"]:checked')).map((c) => c.value);
        if (!sections.length) {
          if (msg) { msg.textContent = "حداقل یک بخش را انتخاب کنید."; msg.classList.add("is-error"); }
          return;
        }
      }

      downloadBtn.disabled = true;
      if (msg) { msg.textContent = "در حال ساخت فایل پشتیبان..."; msg.classList.remove("is-error"); }

      try {
        const res = await fetch("api/backup_create.php", {
          method: "POST",
          headers: { "X-CSRF-Token": csrfToken, "Content-Type": "application/json" },
          body: JSON.stringify({ scope: currentScope === "full" ? "full" : "partial", sections }),
        });

        const contentType = res.headers.get("Content-Type") || "";
        if (contentType.includes("application/json")) {
          const data = await res.json();
          throw new Error(data.message || "ساخت فایل پشتیبان با خطا مواجه شد.");
        }

        const blob = await res.blob();
        const disposition = res.headers.get("Content-Disposition") || "";
        const match = disposition.match(/filename="?([^"]+)"?/);
        const filename = match ? match[1] : "resa-backup.zip";

        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);

        if (msg) { msg.textContent = "فایل پشتیبان دانلود شد."; msg.classList.remove("is-error"); }
        showToast("فایل پشتیبان با موفقیت ساخته شد.");
      } catch (err) {
        if (msg) { msg.textContent = err.message; msg.classList.add("is-error"); }
        showToast(err.message, true);
      } finally {
        downloadBtn.disabled = false;
      }
    });
  }

  function initBackupRestore() {
    const form = document.getElementById("restoreBackupForm");
    if (!form) return;

    const fileInputEl = document.getElementById("restoreBackupFile");
    const fileDropEl = document.getElementById("restoreFileDrop");
    const fileNameEl = document.getElementById("restoreFileName");
    const fileNameDefaultText = fileNameEl ? fileNameEl.textContent : "";
    fileInputEl?.addEventListener("change", () => {
      const picked = fileInputEl.files[0];
      if (picked) {
        if (fileNameEl) fileNameEl.textContent = picked.name;
        fileDropEl?.classList.add("has-file");
      } else {
        if (fileNameEl) fileNameEl.textContent = fileNameDefaultText;
        fileDropEl?.classList.remove("has-file");
      }
    });

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const msg = document.getElementById("restoreBackupMsg");
      const fileInput = document.getElementById("restoreBackupFile");
      const submitBtn = form.querySelector('button[type="submit"]');

      if (!fileInput.files.length) return;

      const confirmed = confirm(
        "این عملیات اطلاعات فعلی جدول‌های موجود در فایل پشتیبان را جایگزین می‌کند و غیرقابل بازگشت است. آیا مطمئن هستید؟"
      );
      if (!confirmed) return;

      submitBtn.disabled = true;
      if (msg) { msg.textContent = "در حال بازیابی، لطفاً صبر کنید..."; msg.classList.remove("is-error"); }

      try {
        const fd = new FormData();
        fd.append("backup_file", fileInput.files[0]);
        const result = await apiPostForm("api/backup_restore.php", fd);
        const d = result.data || {};
        let text = result.message || "بازیابی انجام شد.";
        if (msg) { msg.textContent = text; msg.classList.remove("is-error"); }
        showToast(text);
        if (d.errors && d.errors.length) {
          console.warn("خطاهای بازیابی:", d.errors);
        }
        form.reset();
        if (fileNameEl) fileNameEl.textContent = fileNameDefaultText;
        fileDropEl?.classList.remove("has-file");
      } catch (err) {
        if (msg) { msg.textContent = err.message; msg.classList.add("is-error"); }
        showToast(err.message, true);
      } finally {
        submitBtn.disabled = false;
      }
    });
  }

  document.addEventListener("DOMContentLoaded", () => {
    initMaintenanceSettings();
    initVisitStats();
    loadActivityLog();
    initBackup();
    initBackupRestore();
    initSystemHealth();
    initBackupSchedule();
  });
})();
