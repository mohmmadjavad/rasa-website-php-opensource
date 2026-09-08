/* =========================================================
   admin-dashboard.js — تب «داشبورد» پنل ادمین
   ========================================================= */

(function () {
  "use strict";

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || "";

  document.addEventListener("DOMContentLoaded", () => {
    try {
      initDashboard();
    } catch (err) {
      console.error("[admin-dashboard] initDashboard failed:", err);
    }
  });

  function escapeHtml(str) {
    const div = document.createElement("div");
    div.textContent = str ?? "";
    return div.innerHTML;
  }

  async function apiCall(url) {
    const res = await fetch(url, { headers: { "X-CSRF-Token": csrfToken } });
    try {
      return await res.json();
    } catch (e) {
      throw new Error("پاسخ نامعتبر از سرور دریافت شد.");
    }
  }

  function initDashboard() {
    const grid = document.getElementById("dashKpiGrid");
    if (!grid) return;
    apiCall("api/dashboard_stats.php")
      .then((data) => {
        if (!data.ok) throw new Error(data.message || "خطا در دریافت آمار.");
        renderDashboard(data.data);
      })
      .catch(() => {
        grid.innerHTML = '<div class="empty-state">دریافت آمار داشبورد با خطا مواجه شد.</div>';
      });
  }

  function kpiCard(num, label) {
    return `<div class="stat-card"><div class="stat-card-num">${(num || 0).toLocaleString("fa-IR")}</div><div class="stat-card-label">${label}</div></div>`;
  }

  function renderDashboard(d) {
    const grid = document.getElementById("dashKpiGrid");
    let cards = "";

    if (d.can_view_articles && d.articles) {
      cards += kpiCard(d.articles.total, "کل وبلاگ‌ها");
      cards += kpiCard(d.articles.published, "وبلاگ‌ها منتشرشده");
      cards += kpiCard(d.articles.draft, "پیش‌نویس وبلاگ‌ها");
    }
    if (d.can_view_projects && d.projects) {
      cards += kpiCard(d.projects.total, "کل پروژه‌ها");
      cards += kpiCard(d.projects.published, "پروژه‌های منتشرشده");
    }
    if (d.can_view_comments && d.comments) {
      cards += kpiCard(d.comments.pending, "نظرات در انتظار تایید");
      if (d.comments.spam_suspected > 0) {
        cards += kpiCard(d.comments.spam_suspected, "مشکوک به اسپم");
      }
    }
    if (d.can_view_messages && d.messages) {
      cards += kpiCard(d.messages.unread, "پیام‌های نخوانده");
    }
    if (typeof d.admins_count === "number") {
      cards += kpiCard(d.admins_count, "تعداد ادمین‌ها");
    }

    grid.innerHTML = cards || '<div class="empty-state">داده‌ای برای نمایش وجود ندارد.</div>';

    renderTopList("dashArticlesCard", "dashTopArticles", d.can_view_articles ? d.articles?.top : null, "../article.html?slug=");
    renderTopList("dashProjectsCard", "dashTopProjects", d.can_view_projects ? d.projects?.top : null, "../project.html?slug=");

    if (d.can_view_visits && d.visits) {
      const card = document.getElementById("dashVisitsCard");
      if (card) card.style.display = "";
      renderVisitChart(d.visits);
    }
  }

  function renderTopList(cardId, listId, items, linkPrefix) {
    const card = document.getElementById(cardId);
    const list = document.getElementById(listId);
    if (!card || !list) return;
    if (!items || !items.length) return;
    card.style.display = "";
    list.innerHTML = items
      .map(
        (it) => `
        <li class="dash-top-item">
          <a href="${linkPrefix}${encodeURIComponent(it.slug)}" target="_blank" rel="noopener">${escapeHtml(it.title)}</a>
          <span class="dash-top-views">${(it.views || 0).toLocaleString("fa-IR")} بازدید</span>
        </li>`
      )
      .join("");
  }

  function renderVisitChart(stats) {
    const chartWrap = document.getElementById("dashVisitChart");
    if (!chartWrap) return;
    const days = stats.last_7_days || [];
    const max = Math.max(1, ...days.map((d) => d.count));
    const topCards = `
      <div class="stat-cards-grid" style="margin-bottom:1rem;">
        ${kpiCard(stats.total, "کل بازدیدها")}
        ${kpiCard(stats.today, "بازدید امروز")}
      </div>`;
    const bars =
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
    chartWrap.innerHTML = topCards + bars;
  }
})();
