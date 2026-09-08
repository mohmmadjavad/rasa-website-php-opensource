/* ===========================================================
   projects.js — منطق صفحه‌ی فهرست پروژه‌ها (بازطراحی‌شده)
   تب دسته‌بندی + اسپاتلایت پروژه ویژه + ردیف‌های دسته‌بندی‌شده
   =========================================================== */
   (function () {
    "use strict";
  
    let allProjects = [];
    let categories = [];
    let activeCategory = "";
    let activeBrandSlug = "";
    let activeBrandName = "";
    let searchQuery = "";

    document.addEventListener("DOMContentLoaded", () => {
      if (!document.getElementById("projGroups")) return;

      const params = new URLSearchParams(window.location.search);
      activeCategory = params.get("category") || "";
      activeBrandSlug = params.get("brand") || "";

      wireSearch();
      loadProjects();
    });

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
        console.error("[projects.js] خطا در دریافت اطلاعات از سرور:", url, e);
        return null;
      }
    }

    /* ---------------------------------------------------------
       جستجو (هدر ثابت، بالای صفحه) — دقیقا مثل صفحه وبلاگ‌ها، با این
       تفاوت که این‌جا به‌صورت زنده روی همان لیست پروژه‌های پایین صفحه فیلتر می‌کند
    --------------------------------------------------------- */
    function wireSearch() {
      const input = document.getElementById("projectSearchInput");
      const header = document.getElementById("searchHeader");
      const icon = header?.querySelector("svg");
      if (!input || !header) return;

      let debounceTimer = null;
      input.addEventListener("input", () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
          searchQuery = input.value.trim();
          renderPage();
        }, 250);
      });
      icon?.addEventListener("click", () => input.focus());

      let lastY = window.scrollY;
      let ticking = false;
      window.addEventListener("scroll", () => {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(() => {
          const y = window.scrollY;
          const goingDown = y > lastY;
          if (goingDown && y > 120) {
            header.classList.add("is-hidden");
          } else {
            header.classList.remove("is-hidden");
          }
          if (document.activeElement === input) header.classList.remove("is-hidden");
          lastY = y;
          ticking = false;
        });
      }, { passive: true });
    }

    /* ---------------------------------------------------------
       بارگذاری اولیه‌ی همه‌ی پروژه‌ها + دسته‌بندی‌ها (یک درخواست)
       فیلتر جستجو و برند به‌صورت کاملا کلاینت‌ساید روی همین داده اعمال می‌شود
    --------------------------------------------------------- */
    async function loadProjects() {
      const data = await fetchJSON("api/projects_list.php?per_page=60");
      const emptyEl = document.getElementById("projectsEmpty");

      if (!data) {
        document.getElementById("projTabs").innerHTML = "";
        document.getElementById("projBentoSection").style.display = "none";
        document.getElementById("projectsEmptyMsg").textContent = "خطا در برقراری ارتباط با سرور. لطفاً بعداً دوباره تلاش کنید.";
        emptyEl.style.display = "block";
        return;
      }

      allProjects = data.projects || [];
      categories = data.categories || [];

      if (activeBrandSlug) {
        const withBrand = allProjects.find((p) => (p.brands || []).some((b) => b.slug === activeBrandSlug));
        activeBrandName = withBrand ? (withBrand.brands.find((b) => b.slug === activeBrandSlug) || {}).name || "" : "";
      }

      if (!allProjects.length) {
        document.getElementById("projTabs").innerHTML = "";
        document.getElementById("projBentoSection").style.display = "none";
        emptyEl.style.display = "block";
        return;
      }

      renderTabs();
      renderPage();

      if (activeCategory && !activeBrandSlug && !searchQuery) {
        const target = document.getElementById("cat-" + activeCategory);
        if (target) setTimeout(() => target.scrollIntoView({ behavior: "smooth", block: "start" }), 200);
      }
    }

    /**
     * تصمیم می‌گیرد که نمای عادی (بنتو + ردیف‌های دسته‌بندی‌شده) نمایش داده شود
     * یا نمای فیلترشده‌ی تخت (وقتی جستجو یا فیلتر برند فعال باشد) — هر دو داخل
     * همان بخش «پروژه‌ها» در پایین صفحه رندر می‌شوند، نه در یک باکس جداگانه‌ی عجیب.
     */
    function renderPage() {
      const hasFilter = !!(searchQuery || activeBrandSlug);
      document.getElementById("projBentoSection").style.display = hasFilter ? "none" : "";

      if (hasFilter) {
        renderFilteredFlatList();
      } else {
        renderBento();
        renderGroups();
      }
    }

    function clearAllFilters() {
      searchQuery = "";
      activeBrandSlug = "";
      activeBrandName = "";
      const input = document.getElementById("projectSearchInput");
      if (input) input.value = "";
      history.replaceState(null, "", "projects.html");
      renderPage();
    }

    /* ---------------------------------------------------------
       نمای تخت فیلترشده — همان محل همیشگی «پروژه‌ها»‌ی پایین صفحه،
       فقط با نتایج جستجو/برند به‌جای ردیف‌های دسته‌بندی
    --------------------------------------------------------- */
    function renderFilteredFlatList() {
      const wrap = document.getElementById("projGroups");
      const emptyEl = document.getElementById("projectsEmpty");
      emptyEl.style.display = "none";

      let list = allProjects;
      if (activeBrandSlug) {
        list = list.filter((p) => (p.brands || []).some((b) => b.slug === activeBrandSlug));
      }
      if (searchQuery) {
        const q = searchQuery.toLowerCase();
        list = list.filter((p) => (p.title || "").toLowerCase().includes(q) || (p.excerpt || "").toLowerCase().includes(q));
      }

      let heading = "نتایج جستجو";
      if (activeBrandSlug) heading = activeBrandName ? `پروژه‌های برند «${activeBrandName}»` : "پروژه‌های این برند";
      if (activeBrandSlug && searchQuery) heading += ` — جستجوی «${escapeHtml(searchQuery)}»`;
      else if (searchQuery) heading = `جستجوی «${escapeHtml(searchQuery)}»`;

      const headHtml = `
        <div class="proj-filter-head">
          <h2>${heading}</h2>
          <button type="button" class="proj-filter-clear" id="projClearFiltersBtn">مشاهده همه پروژه‌ها ×</button>
        </div>`;

      if (!list.length) {
        wrap.innerHTML = headHtml;
        document.getElementById("projClearFiltersBtn")?.addEventListener("click", clearAllFilters);
        document.getElementById("projectsEmptyMsg").textContent = activeBrandSlug
          ? `هنوز پروژه‌ای برای این برند منتشر نشده.`
          : "پروژه‌ای با این مشخصات پیدا نشد.";
        emptyEl.style.display = "block";
        return;
      }

      wrap.innerHTML = headHtml + `<div class="proj-filter-grid">${list.map((p, i) => projectCardHtml(p, "var(--c-teal)", i)).join("")}</div>`;
      document.getElementById("projClearFiltersBtn")?.addEventListener("click", clearAllFilters);
    }
  
    /* ---------------------------------------------------------
       تب‌های دسته‌بندی
    --------------------------------------------------------- */
    const CAT_COLORS = ["var(--cat-a)", "var(--cat-b)", "var(--cat-c)", "var(--cat-d)", "var(--cat-e)", "var(--cat-f)"];
  
    function catColor(index) {
      return CAT_COLORS[index % CAT_COLORS.length];
    }
  
    function renderTabs() {
      const wrap = document.getElementById("projTabs");
      let html = `<button type="button" class="proj-tab ${activeCategory === "" ? "is-active" : ""}" data-slug="">
          <span class="proj-tab-dot" style="--cat-color:var(--c-teal)"></span>همه
        </button>`;
      html += categories
        .map(
          (c, i) => `
          <button type="button" class="proj-tab ${activeCategory === c.slug ? "is-active" : ""}" data-slug="${escapeHtml(c.slug)}" style="--cat-color:${catColor(i)}">
            <span class="proj-tab-dot"></span>${escapeHtml(c.name)}
          </button>`
        )
        .join("");
      wrap.innerHTML = html;
  
      wrap.querySelectorAll(".proj-tab").forEach((btn) => {
        btn.addEventListener("click", () => {
          activeCategory = btn.dataset.slug;
          wrap.querySelectorAll(".proj-tab").forEach((b) => b.classList.toggle("is-active", b === btn));

          if (searchQuery || activeBrandSlug) {
            searchQuery = "";
            activeBrandSlug = "";
            activeBrandName = "";
            const input = document.getElementById("projectSearchInput");
            if (input) input.value = "";
            history.replaceState(null, "", "projects.html");
            renderPage();
          }

          if (activeCategory === "") {
            window.scrollTo({ top: document.querySelector(".proj-bento-section")?.offsetTop - 90 || 0, behavior: "smooth" });
          } else {
            const target = document.getElementById("cat-" + activeCategory);
            target?.scrollIntoView({ behavior: "smooth", block: "start" });
          }
        });
      });
    }
  
    /* ---------------------------------------------------------
       رسانه (عکس/ویدیو) مشترک
    --------------------------------------------------------- */
    function mediaHtml(p, tag) {
      const isVideo = p.cover_type === "video" && p.cover_video;
      if (isVideo) {
        return `<video src="${p.cover_video}" muted loop playsinline autoplay preload="metadata"></video>`;
      }
      if (p.cover_image) {
        return `<img src="${p.cover_image}" alt="${escapeHtml(p.title)}" loading="lazy">`;
      }
      return "";
    }
  
    function teamAvatarsHtml(team, max, sizeClass) {
      const list = (team || []).slice(0, max);
      const extra = (team || []).length - list.length;
      let html = list
        .map((t) => {
          const inner = t.avatar
            ? `<img src="${t.avatar}" alt="${escapeHtml(t.name || "")}" title="${escapeHtml(t.name || "")}">`
            : `<span title="${escapeHtml(t.name || "")}">${escapeHtml((t.name || "؟").trim().charAt(0))}</span>`;
          return t.username
            ? `<a href="resume.html?user=${encodeURIComponent(t.username)}" class="team-avatar-link" title="${escapeHtml(t.name || "")}" onclick="event.stopPropagation()">${inner}</a>`
            : inner;
        })
        .join("");
      if (extra > 0) html += `<span class="team-more">+${extra.toLocaleString("fa-IR")}</span>`;
      return html;
    }
  
    /* ---------------------------------------------------------
       بنتو — گزیده‌ی تصادفی پروژه‌ها، چیدمان نامتقارن
       هر بار که صفحه لود می‌شود ترکیب متفاوتی از پروژه‌ها نمایش داده می‌شود
    --------------------------------------------------------- */
    function shuffle(arr) {
      const a = arr.slice();
      for (let i = a.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [a[i], a[j]] = [a[j], a[i]];
      }
      return a;
    }

    function renderBento() {
      const section = document.getElementById("projBentoSection");
      const grid = document.getElementById("projBentoGrid");
      const slots = ["bento-a", "bento-b", "bento-c", "bento-d", "bento-e"];
      const items = shuffle(allProjects).slice(0, Math.min(slots.length, allProjects.length));
      if (!items.length) { section.style.display = "none"; return; }

      grid.innerHTML = items
        .map((p, i) => {
          const isVideo = p.cover_type === "video" && p.cover_video;
          return `
          <a href="project.html?slug=${encodeURIComponent(p.slug)}" class="proj-bento-item ${slots[i]}">
            <div class="proj-bento-media">${mediaHtml(p)}</div>
            ${isVideo ? `<span class="proj-bento-play">${ICON_PLAY}</span>` : ""}
            ${p.category_name ? `<span class="proj-bento-tag">${escapeHtml(p.category_name)}</span>` : ""}
            <h2 class="proj-bento-title">${escapeHtml(p.title)}</h2>
          </a>`;
        })
        .join("");
    }
  
    /* ---------------------------------------------------------
       کارت عمودی
    --------------------------------------------------------- */
    function projectCardHtml(p, catColorVar, index) {
      const isVideo = p.cover_type === "video" && p.cover_video;
      return `
        <div class="proj-card">
          <a href="project.html?slug=${encodeURIComponent(p.slug)}" class="proj-card-linkarea" aria-label="${escapeHtml(p.title)}"></a>
          <div class="proj-card-media">${mediaHtml(p)}</div>
          ${isVideo ? `<span class="proj-card-video-badge">${ICON_PLAY}</span>` : ""}
          <div class="proj-card-team">${teamAvatarsHtml(p.team, 3)}</div>
          <span class="proj-card-index">${(index + 1).toLocaleString("fa-IR")}</span>
          <div class="proj-card-body">
            ${p.category_name ? `<span class="proj-card-cat" style="--cat-color:${catColorVar}">${escapeHtml(p.category_name)}</span>` : ""}
            <h3 class="proj-card-title">${escapeHtml(p.title)}</h3>
          </div>
        </div>`;
    }
  
    /* ---------------------------------------------------------
       ردیف‌های دسته‌بندی‌شده — به‌سبک فروشگاهی، هرکدام یک اسکرول افقی
    --------------------------------------------------------- */
    function renderGroups() {
      const wrap = document.getElementById("projGroups");

      const byCat = new Map();
      allProjects.forEach((p) => {
        const key = p.category_slug || "__none";
        if (!byCat.has(key)) byCat.set(key, []);
        byCat.get(key).push(p);
      });
  
      const orderedCats = categories.filter((c) => byCat.has(c.slug));
      if (byCat.has("__none")) orderedCats.push({ slug: "__none", name: "متفرقه" });
  
      if (!orderedCats.length) { wrap.innerHTML = ""; return; }
  
      wrap.innerHTML = orderedCats
        .map((c) => {
          const idx = categories.findIndex((x) => x.slug === c.slug);
          const color = catColor(idx >= 0 ? idx : 0);
          const items = byCat.get(c.slug) || [];
          return `
          <div class="proj-group" id="cat-${escapeHtml(c.slug)}" style="--cat-color:${color}">
            <div class="proj-group-head">
              <span class="proj-group-count">${items.length.toLocaleString("fa-IR")} پروژه</span>
              <h2>${escapeHtml(c.name)}</h2>
            </div>
            <div class="proj-scroll-row" data-slug="${escapeHtml(c.slug)}">${items.map((p, i) => projectCardHtml(p, color, i)).join("")}</div>
          </div>`;
        })
        .join("");
  
      wrap.querySelectorAll(".proj-scroll-row").forEach((row, i) => {
        initAutoScroller(row, 3200 + i * 250);
      });
    }
  
    /* ---------------------------------------------------------
       اسکرول خودکار افقی — با توقف روی تعامل کاربر
    --------------------------------------------------------- */
    function initAutoScroller(container, intervalMs) {
      if (!container) return;
      const items = Array.from(container.children);
      if (items.length < 2) return;
  
      let idx = 0;
      let timer = null;
      let resumeTimeout = null;
  
      function next() {
        idx = (idx + 1) % items.length;
        const y = window.scrollY;
        items[idx].scrollIntoView({ behavior: "smooth", inline: "start", block: "nearest" });
        window.scrollTo({ top: y, left: window.scrollX, behavior: "auto" });
      }
  
      function start() {
        stop();
        timer = setInterval(next, intervalMs);
      }
      function stop() {
        if (timer) clearInterval(timer);
        timer = null;
      }
      function pauseThenResume() {
        stop();
        clearTimeout(resumeTimeout);
        resumeTimeout = setTimeout(start, intervalMs * 1.6);
      }
  
      container.addEventListener("pointerdown", pauseThenResume, { passive: true });
      container.addEventListener("wheel", pauseThenResume, { passive: true });
      container.addEventListener("touchstart", pauseThenResume, { passive: true });
  
      start();
    }
  
    const ICON_PLAY = `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7Z"/></svg>`;
    const ICON_STAR = `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.6l2.9 6.2 6.6.7-4.9 4.6 1.3 6.6-5.9-3.3-5.9 3.3 1.3-6.6-4.9-4.6 6.6-.7Z"/></svg>`;
  })();