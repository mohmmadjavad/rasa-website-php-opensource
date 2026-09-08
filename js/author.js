/* ===========================================================
   author.js — رفع باگ تغییر تب‌ها و محاسبه دقیق انیمیشن خط
   =========================================================== */
(function () {
  "use strict";

  let state = {
    username: "",
    id: 0,
    activeTab: "articles",
    page: 1,
    query: "",
    totalPages: 1,
  };
  let searchDebounceTimer = null;

  document.addEventListener("DOMContentLoaded", () => {
    const params = new URLSearchParams(window.location.search);
    state.username = (params.get("user") || "").trim();

    wireSearch();

    if (!state.username) {
      showNotFound();
      return;
    }

    loadAuthor();

    // هندلر کلیک تب‌ها
    const btnArticles = document.getElementById("tabBtnArticles");
    const btnProjects = document.getElementById("tabBtnProjects");

    btnArticles?.addEventListener("click", (e) => {
      e.stopPropagation();
      if (state.activeTab !== "articles") setActiveTab("articles");
    });

    btnProjects?.addEventListener("click", (e) => {
      e.stopPropagation();
      if (state.activeTab !== "projects") setActiveTab("projects");
    });

    window.addEventListener("resize", updateTabIndicator);

    document.getElementById("authorSearchInput")?.addEventListener("input", (e) => {
      const val = e.target.value;
      clearTimeout(searchDebounceTimer);
      searchDebounceTimer = setTimeout(() => {
        state.query = val.trim();
        state.page = 1;
        loadGrid(false);
      }, 350);
    });

    document.getElementById("authorPinMore")?.addEventListener("click", () => {
      state.page += 1;
      loadGrid(true);
    });
  });

  /* -------------------- محاسبه دقیق مکان خط متحرک -------------------- */
  function updateTabIndicator() {
    const activeTabBtn = document.querySelector('.author-tab.is-active');
    const indicator = document.getElementById('tabIndicator');
    const tabsContainer = document.getElementById('authorTabs');

    if (!activeTabBtn || !indicator || !tabsContainer) return;

    const textSpan = activeTabBtn.querySelector('.tab-text');
    if (!textSpan) return;

    const containerRect = tabsContainer.getBoundingClientRect();
    const textRect = textSpan.getBoundingClientRect();

    const textWidth = textRect.width;
    // محاسبه offset افقی مستقل از RTL و LTR
    const leftOffset = textRect.left - containerRect.left;

    indicator.style.width = `${textWidth}px`;
    indicator.style.transform = `translateX(${leftOffset}px)`;
  }

  /* -------------------- ابزارهای عمومی -------------------- */
  function escapeHtml(str) {
    const div = document.createElement("div");
    div.textContent = str ?? "";
    return div.innerHTML;
  }

  async function fetchJSON(url) {
    try {
      const res = await fetch(url);
      if (!res.ok) throw new Error("bad status " + res.status);
      const data = await res.json();
      if (!data.ok) throw new Error(data.message || "bad response");
      return data.data;
    } catch (e) {
      console.error("[author.js] خطا در دریافت اطلاعات:", url, e);
      return null;
    }
  }

  function initialsOf(name) {
    const parts = (name || "").trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return "ر";
    return parts[0][0];
  }

  function showNotFound() {
    document.getElementById("authorLoading").style.display = "none";
    document.getElementById("authorNotFound").style.display = "flex";
  }

  /* -------------------- جستجوی هدر -------------------- */
  function goToSearch() {
    const input = document.getElementById("searchInput");
    const q = (input?.value || "").trim();
    const params = new URLSearchParams();
    if (q) params.set("q", q);
    window.location.href = "search.html" + (params.toString() ? "?" + params.toString() : "");
  }

  function wireSearch() {
    const input = document.getElementById("searchInput");
    const header = document.getElementById("searchHeader");
    const icon = header?.querySelector("svg");

    input?.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        goToSearch();
      }
    });
    icon?.addEventListener("click", goToSearch);
    if (icon) icon.style.cursor = "pointer";
  }

  /* -------------------- آیکون‌های شبکه‌های اجتماعی -------------------- */
  const SOCIAL_SVG = {
    telegram: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="m21 4-9 16-3-7-7-3Z"/><path d="M21 4 8.5 13.2"/></svg>',
    whatsapp: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3.5h3l1.5 4-2 2a11 11 0 0 0 5.5 5.5l2-2 4 1.5v3a2 2 0 0 1-2.2 2A17 17 0 0 1 4 6.2 2 2 0 0 1 6 3.5Z"/></svg>',
    instagram: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="3.6"/><circle cx="17" cy="7" r="1"/></svg>',
    pinterest: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 18c.6-1.7 1.6-5.3 1.6-5.3M12 8.5c2.2 0 3.5 1.4 3.5 3.3 0 2.4-1.3 4.4-3.2 4.4-1 0-1.8-.6-2-1.3"/></svg>',
    x: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="m4 4 16 16M20 4 4 20"/></svg>',
    linkedin: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3.5" y="3.5" width="17" height="17" rx="3"/><circle cx="8" cy="9" r="1"/><path d="M8 11.5v5.5M12 17v-3.5c0-1.2 1-2 2-2s2 .8 2 2V17"/></svg>',
    github: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19c-4.3 1.4-4.3-2.5-6-3m12 5v-3.4c0-1 .1-1.4-.5-2 2.8-.3 5.5-1.4 5.5-6a4.6 4.6 0 0 0-1.3-3.2 4.2 4.2 0 0 0-.1-3.2s-1-.3-3.4 1.2a11.8 11.8 0 0 0-6.2 0C6.6 3 5.6 3.3 5.6 3.3a4.2 4.2 0 0 0-.1 3.2A4.6 4.6 0 0 0 4.2 9.7c0 4.6 2.7 5.7 5.5 6-.6.6-.6 1.2-.5 2V21"/></svg>',
    youtube: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="6" width="19" height="12" rx="4"/><path d="m10.5 9.5 5 2.5-5 2.5Z" fill="currentColor" stroke="none"/></svg>',
    website: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.7 4 6 4 9s-1.5 6.3-4 9c-2.5-2.7-4-6-4-9s1.5-6.3 4-9Z"/></svg>',
    email: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3.5" y="5.5" width="17" height="13" rx="2"/><path d="M4.5 7 12 13l7.5-6"/></svg>',
    phone: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3.5h3l1.5 4-2 2a11 11 0 0 0 5.5 5.5l2-2 4 1.5v3a2 2 0 0 1-2.2 2A17 17 0 0 1 4 6.2 2 2 0 0 1 6 3.5Z"/></svg>',
  };

  const SHARE_SVG =
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="2.6"/><circle cx="6" cy="12" r="2.6"/><circle cx="18" cy="19" r="2.6"/><path d="m8.3 10.7 7.4-4.1M8.3 13.3l7.4 4.1"/></svg>';

  /* -------------------- نشان‌های دستاورد -------------------- */
  const BADGE_ICONS = {
    pen: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L18.5 9.5a2.1 2.1 0 0 0-3-3L5 17v3Z"/><path d="M13.5 8 16 10.5"/></svg>',
    layers: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5"/></svg>',
    eye: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.6"/></svg>',
  };

  const BADGE_TIERS = ["bronze", "silver", "gold", "diamond"];
  const BADGE_TIER_LABELS = {
    bronze: "برنزی",
    silver: "نقره‌ای",
    gold: "طلایی",
    diamond: "الماسی",
  };

  const BADGE_CATEGORIES = [
    {
      key: "articles",
      label: "نویسنده",
      icon: BADGE_ICONS.pen,
      thresholds: { bronze: 15, silver: 45, gold: 90, diamond: 180 },
    },
    {
      key: "projects",
      label: "پروژه‌محور",
      icon: BADGE_ICONS.layers,
      thresholds: { bronze: 15, silver: 30, gold: 60, diamond: 120 },
    },
    {
      key: "views",
      label: "محبوب",
      icon: BADGE_ICONS.eye,
      thresholds: { bronze: 1500, silver: 6000, gold: 30000, diamond: 150000 },
    },
  ];

  function renderBadges(author) {
    const badgesEl = document.getElementById("authorBadges");
    if (!badgesEl) return;

    const counts = {
      articles: author.article_count || 0,
      projects: author.project_count || 0,
      views: author.total_views || 0,
    };

    const items = [];

    BADGE_CATEGORIES.forEach((cat) => {
      let tierIndex = -1;
      BADGE_TIERS.forEach((tier, idx) => {
        if (counts[cat.key] >= cat.thresholds[tier]) tierIndex = idx;
      });
      if (tierIndex === -1) return; // هنوز به اولین سطح نرسیده

      const tier = BADGE_TIERS[tierIndex];

      items.push(`
        <div class="author-badge tier-${tier}" title="${escapeHtml(cat.label)} ${escapeHtml(BADGE_TIER_LABELS[tier])}">
          <span class="author-badge-icon">${cat.icon}</span>
          <span class="author-badge-label">${escapeHtml(cat.label)}</span>
        </div>`);
    });

    if (!items.length) {
      badgesEl.style.display = "none";
      badgesEl.innerHTML = "";
      return;
    }

    badgesEl.innerHTML = items.join("");
    badgesEl.style.display = "flex";
  }

  /* -------------------- بخش «آخرین فعالیت» -------------------- */
  function relativeTimeFa(dateStr) {
    if (!dateStr) return "";
    const then = new Date(String(dateStr).replace(" ", "T"));
    if (isNaN(then.getTime())) return "";

    const diffDays = Math.floor((Date.now() - then.getTime()) / 86400000);

    if (diffDays <= 0) return "امروز";
    if (diffDays === 1) return "دیروز";
    if (diffDays < 7) return `${diffDays.toLocaleString("fa-IR")} روز پیش`;
    if (diffDays < 30) return `${Math.floor(diffDays / 7).toLocaleString("fa-IR")} هفته پیش`;
    if (diffDays < 365) return `${Math.floor(diffDays / 30).toLocaleString("fa-IR")} ماه پیش`;
    return `${Math.floor(diffDays / 365).toLocaleString("fa-IR")} سال پیش`;
  }

  async function loadRecentActivity(authorId) {
    const wrap = document.getElementById("authorRecentActivity");
    const textEl = document.getElementById("authorRecentActivityText");
    if (!wrap || !textEl) return;

    const [articlesData, projectsData] = await Promise.all([
      fetchJSON(`api/articles_list.php?author=${authorId}&page=1`),
      fetchJSON(`api/projects_list.php?author=${authorId}&page=1&per_page=1`),
    ]);

    const latestArticle = (articlesData && articlesData.articles && articlesData.articles[0]) || null;
    const latestProject = (projectsData && projectsData.projects && projectsData.projects[0]) || null;

    let latest = null;
    let isProject = false;

    if (latestArticle && latestProject) {
      const artTime = new Date(String(latestArticle.published_at).replace(" ", "T")).getTime();
      const projTime = new Date(String(latestProject.published_at).replace(" ", "T")).getTime();
      if (projTime > artTime) {
        latest = latestProject;
        isProject = true;
      } else {
        latest = latestArticle;
      }
    } else if (latestProject) {
      latest = latestProject;
      isProject = true;
    } else if (latestArticle) {
      latest = latestArticle;
    }

    if (!latest || !latest.published_at) {
      wrap.style.display = "none";
      return;
    }

    const when = relativeTimeFa(latest.published_at);
    const action = isProject ? "یک پروژه جدید منتشر کرد" : "یک پست جدید منتشر کرد";
    textEl.textContent = `${when} ${action}`;
    wrap.style.display = "flex";
  }

  /* -------------------- اشتراک‌گذاری پروفایل -------------------- */
  function showToast(message) {
    let toast = document.getElementById("resaToast");
    if (!toast) {
      toast = document.createElement("div");
      toast.id = "resaToast";
      toast.className = "resa-toast";
      document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.classList.add("is-visible");
    clearTimeout(toast._hideTimer);
    toast._hideTimer = setTimeout(() => toast.classList.remove("is-visible"), 2200);
  }

  function wireShareButton() {
    const btn = document.getElementById("authorShareBtn");
    if (!btn) return;

    btn.addEventListener("click", async () => {
      const shareData = {
        title: document.getElementById("authorHeroName")?.textContent || "پروفایل رسا تیم",
        text: document.getElementById("authorHeroRole")?.textContent || "",
        url: window.location.href,
      };

      if (navigator.share) {
        try {
          await navigator.share(shareData);
        } catch (e) {
          /* کاربر خودش لغو کرده - نیازی به خطا نیست */
        }
        return;
      }

      try {
        await navigator.clipboard.writeText(shareData.url);
        showToast("لینک پروفایل کپی شد");
      } catch (e) {
        showToast("امکان کپی لینک نبود");
      }
    });
  }

  /* -------------------- بارگذاری پروفایل -------------------- */
  async function loadAuthor() {
    const data = await fetchJSON(`api/author_single.php?user=${encodeURIComponent(state.username)}`);
    if (!data || !data.author) {
      showNotFound();
      return;
    }
    state.id = data.author.id;
    renderHeader(data.author);
    renderBadges(data.author);
    loadRecentActivity(state.id);

    document.getElementById("authorLoading").style.display = "none";
    document.getElementById("authorContent").style.display = "block";

    setActiveTab("articles");
  }

  function renderHeader(author) {
    document.getElementById("authorHeroName").textContent = author.display_name;

    const roleEl = document.getElementById("authorHeroRole");
    if (author.job_title) {
      roleEl.textContent = author.job_title;
      roleEl.style.display = "block";
    }

    const bioEl = document.getElementById("authorHeroBio");
    if (author.bio_short) {
      bioEl.textContent = author.bio_short;
      bioEl.style.display = "block";
    }

    const avatarImg = document.getElementById("authorHeroAvatarImg");
    const initialEl = document.getElementById("authorHeroInitial");
    if (author.avatar) {
      avatarImg.src = author.avatar;
      avatarImg.style.display = "block";
      initialEl.style.display = "none";
    } else {
      initialEl.textContent = initialsOf(author.display_name);
      initialEl.style.display = "flex";
      avatarImg.style.display = "none";
    }

    document.getElementById("authorTabArticlesCount").textContent = (author.article_count || 0).toLocaleString("fa-IR");
    document.getElementById("authorTabProjectsCount").textContent = (author.project_count || 0).toLocaleString("fa-IR");

    const tagsEl = document.getElementById("authorSocialTags");
    tagsEl.innerHTML = (author.socials || [])
      .map(
        (s) => `
      <a href="${s.url}" target="_blank" rel="noopener" class="author-social-tag">
        <span class="author-social-tag-icon">${SOCIAL_SVG[s.key] || SOCIAL_SVG.website}</span>
        ${escapeHtml(s.label)}
      </a>`
      )
      .join("");

    tagsEl.insertAdjacentHTML(
      "beforeend",
      `<button type="button" id="authorShareBtn" class="author-social-tag author-share-btn">
        <span class="author-social-tag-icon">${SHARE_SVG}</span>
        اشتراک‌گذاری
      </button>`
    );
    wireShareButton();
  }

  /* -------------------- تغییر تب -------------------- */
  function setActiveTab(tab) {
    state.activeTab = tab;
    state.page = 1;
    state.query = "";

    const btnArticles = document.getElementById("tabBtnArticles");
    const btnProjects = document.getElementById("tabBtnProjects");

    if (tab === "articles") {
      btnArticles?.classList.add("is-active");
      btnProjects?.classList.remove("is-active");
    } else {
      btnProjects?.classList.add("is-active");
      btnArticles?.classList.remove("is-active");
    }

    // به‌روزرسانی خط انیمیشنی زیر تب فعال
    requestAnimationFrame(updateTabIndicator);

    const searchInput = document.getElementById("authorSearchInput");
    if (searchInput) {
      searchInput.value = "";
      searchInput.placeholder = tab === "projects" ? "جستجو در پروژه‌ها..." : "جستجو در وبلاگ‌ها...";
    }

    document.getElementById("authorPinEmptyText").textContent =
      tab === "projects" ? "هنوز پروژه‌ای ثبت نشده است." : "هنوز وبلاگی ثبت نشده است.";

    loadGrid(false);
  }

  /* -------------------- ساخت کاشی گرید -------------------- */
  function pinTileHtml(item, type) {
    const isProject = type === "projects";
    const href = isProject ? `project.html?slug=${encodeURIComponent(item.slug)}` : `article.html?slug=${encodeURIComponent(item.slug)}`;
    const isVideo = isProject && item.cover_type === "video" && item.cover_video;

    const media = item.cover_image
      ? `<img src="${item.cover_image}" alt="${escapeHtml(item.title)}" loading="lazy">`
      : (isVideo ? `<video src="${item.cover_video}" muted loop playsinline preload="metadata"></video>` : "");

    return `
      <a href="${href}" class="pin-tile">
        ${media}
        <span class="pin-tile-badge">${isProject ? "پروژه" : "وبلاگ"}</span>
        <span class="pin-tile-overlay"><span class="pin-tile-title">${escapeHtml(item.title)}</span></span>
      </a>`;
  }

  /* -------------------- بارگذاری محتوای گرید -------------------- */
  async function loadGrid(append) {
    const grid = document.getElementById("authorPinGrid");
    const emptyEl = document.getElementById("authorPinEmpty");
    const moreBtn = document.getElementById("authorPinMore");

    if (!append) {
      grid.innerHTML = '<div class="list-skel"></div><div class="list-skel"></div>';
      emptyEl.style.display = "none";
    }

    const isProjects = state.activeTab === "projects";
    const params = new URLSearchParams();
    params.set("author", state.id);
    params.set("page", state.page);
    if (state.query) params.set("q", state.query);
    if (isProjects) params.set("per_page", 12);

    const endpoint = isProjects ? "api/projects_list.php" : "api/articles_list.php";
    const data = await fetchJSON(endpoint + "?" + params.toString());
    const items = isProjects ? (data && data.projects) || [] : (data && data.articles) || [];

    if (!items.length) {
      if (!append) {
        grid.innerHTML = "";
        emptyEl.style.display = "block";
      }
      moreBtn.style.display = "none";
      return;
    }

    const html = items.map((item) => pinTileHtml(item, state.activeTab)).join("");
    grid.innerHTML = append ? grid.innerHTML + html : html;

    state.totalPages = (data && data.total_pages) || 1;
    moreBtn.style.display = state.page < state.totalPages ? "inline-flex" : "none";
  }
})();