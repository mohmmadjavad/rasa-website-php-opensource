/* ===========================================================
   article.js — منطق صفحه‌ی تکی وبلاگ
   =========================================================== */
   (function () {
    "use strict";
  
    document.addEventListener("DOMContentLoaded", () => {
      if (!document.getElementById("articlePage")) return;
      const params = new URLSearchParams(window.location.search);
      const previewToken = params.get("preview_token");
      if (previewToken) {
        loadArticlePreview(previewToken);
        initReadingProgress();
        return;
      }
      const slug = params.get("slug");
      if (!slug) {
        showNotFound();
        return;
      }
      loadArticle(slug);
      initReadingProgress();
    });
  
    function escapeHtml(str) {
      const div = document.createElement("div");
      div.textContent = str ?? "";
      return div.innerHTML;
    }
  
    const ICONS = {
      rasa: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8c3-3 9-3 12 0M4 12.5c4.4-4 11.6-4 16 0M8 17c2-1.6 6-1.6 8 0"/><circle cx="12" cy="20" r="1.1" fill="currentColor" stroke="none"/></svg>`,
      rasabaz: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="8" width="19" height="10" rx="5"/><path d="M7.5 11v4M5.5 13h4"/><circle cx="16" cy="11.6" r="1" fill="currentColor" stroke="none"/><circle cx="18.2" cy="14" r="1" fill="currentColor" stroke="none"/></svg>`,
      raysanes: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 2.5h6M10 2.5v5.6L5.5 16a2.4 2.4 0 0 0 2.1 3.5h8.8a2.4 2.4 0 0 0 2.1-3.5L14 8.1V2.5"/><path d="M7.5 14.5h9"/></svg>`,
      default: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3.5h9L19 8v12.5a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1Z"/><path d="M9 12h6M9 16h6"/></svg>`,
    };
  
    function categoryIconHtml(iconField, slug) {
      if (iconField && (iconField.startsWith("assets/") || /^https?:\/\//.test(iconField))) {
        return `<img src="${iconField}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">`;
      }
      return (iconField && ICONS[iconField]) ? ICONS[iconField] : (ICONS[slug] || ICONS.default);
    }
  
    function formatDate(isoLike) {
      if (!isoLike) return "";
      try {
        const d = new Date(isoLike.replace(" ", "T"));
        return d.toLocaleDateString("fa-IR", { year: "numeric", month: "long", day: "numeric" });
      } catch (e) {
        return isoLike;
      }
    }
  
    function showNotFound() {
      document.getElementById("articleLoading").style.display = "none";
      document.getElementById("articleNotFound").style.display = "flex";
    }
  
    async function loadArticle(slug) {
      try {
        const res = await fetch("api/article_single.php?slug=" + encodeURIComponent(slug));
        const data = await res.json();
        if (!data.ok) {
          showNotFound();
          return;
        }
        renderArticle(data.data.article);
      } catch (err) {
        showNotFound();
      }
    }

    async function loadArticlePreview(token) {
      try {
        const res = await fetch("api/article_preview.php?token=" + encodeURIComponent(token));
        const data = await res.json();
        if (!data.ok) {
          showNotFound();
          return;
        }
        showPreviewBanner();
        renderArticle(data.data.article, true);
      } catch (err) {
        showNotFound();
      }
    }

    function showPreviewBanner() {
      const bar = document.createElement("div");
      bar.textContent = "🔍 حالت پیش‌نمایش — این نسخه هنوز منتشر نشده و فقط برای شماست.";
      bar.style.cssText =
        "position:sticky;top:0;z-index:9999;background:#0c8d8d;color:#fff;text-align:center;" +
        "padding:.6rem 1rem;font-size:.85rem;font-weight:700;";
      document.body.prepend(bar);
    }
  
    function initialsOf(name) {
      if (!name) return "ر";
      return name.trim().charAt(0);
    }
  
    function renderArticle(a, isPreview) {
      document.getElementById("articleLoading").style.display = "none";
      const content = document.getElementById("articleContent");
      content.style.display = "block";
  
      document.title = (a.meta_title || a.title) + " | رسا تیم";
      document.getElementById("pageTitle").textContent = (a.meta_title || a.title) + " | رویایی رsa";
      const desc = a.meta_description || a.excerpt || "";
      document.getElementById("pageDescription").setAttribute("content", desc);
      document.getElementById("ogTitle").setAttribute("content", a.title);
      document.getElementById("ogDescription").setAttribute("content", desc);
      if (a.cover_image) document.getElementById("ogImage").setAttribute("content", window.location.origin + "/" + a.cover_image);
  
      const heroImg = document.getElementById("articleHeroImg");
      if (a.cover_image) {
        heroImg.src = a.cover_image;
        heroImg.alt = a.title;
      } else {
        document.getElementById("articleHero").style.background = "linear-gradient(135deg, var(--c-deep), var(--c-ink))";
      }
  
      if (a.category_name) {
        const badge = document.getElementById("articleCatBadge");
        badge.href = `search.html?category=${encodeURIComponent(a.category_slug)}`;
        document.getElementById("articleCatBadgeIcon").innerHTML = categoryIconHtml(a.category_icon, a.category_slug);
        document.getElementById("articleCatBadgeName").textContent = a.category_name;
        const descEl = document.getElementById("articleCatBadgeDesc");
        if (a.category_description) {
          descEl.textContent = a.category_description;
          descEl.style.display = "block";
        } else {
          descEl.style.display = "none";
        }
        badge.style.display = "inline-flex";
      }
  
      document.getElementById("articleTitle").textContent = a.title;
  
      const heroAvatarEl = document.getElementById("authorAvatar");
      const heroProfileAvatar = a.author_profile && a.author_profile.avatar;
      if (heroProfileAvatar) {
        heroAvatarEl.innerHTML = `<img src="${heroProfileAvatar}" alt="">`;
      } else {
        heroAvatarEl.textContent = initialsOf(a.author);
      }
  
      document.getElementById("articleAuthor").textContent = a.author || "رسا تیم";
      document.getElementById("articleDate").innerHTML =
        `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M3.5 9.5h17M8 3v3.5M16 3v3.5"/></svg><span>${formatDate(a.published_at)}</span>`;
      document.getElementById("articleReadingTime").innerHTML =
        `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg><span>${(a.reading_time || 1).toLocaleString("fa-IR")} دقیقه مطالعه</span>`;
      document.getElementById("articleViews").innerHTML =
        `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.6"/></svg><span>${(a.views || 0).toLocaleString("fa-IR")} بازدید</span>`;
  
      document.getElementById("articleProse").innerHTML = a.content || "";
  
      const catCloud = document.getElementById("articleCategoriesCloud");
      let hasCategoriesContent = false;
  
      if (a.category_slug) {
        const mainCard = document.getElementById("mainCategoryCard");
        mainCard.href = `search.html?category=${encodeURIComponent(a.category_slug)}`;
        document.getElementById("mainCategoryIcon").innerHTML = categoryIconHtml(a.category_icon, a.category_slug);
        document.getElementById("mainCategoryName").textContent = a.category_name;
        const mainDescEl = document.getElementById("mainCategoryDesc");
        if (a.category_description) {
          mainDescEl.textContent = a.category_description;
          mainDescEl.style.display = "block";
        } else {
          mainDescEl.style.display = "none";
        }
        mainCard.style.display = "flex";
        hasCategoriesContent = true;
      }
  
      const subChipsHtml = (a.subcategories || [])
        .map((sc) => `<a href="search.html?subcategory=${encodeURIComponent(sc.slug)}" class="category-chip">${escapeHtml(sc.name)}</a>`)
        .join("");
      catCloud.innerHTML = subChipsHtml;
      if (subChipsHtml) hasCategoriesContent = true;
  
      if (hasCategoriesContent) {
        document.getElementById("categoriesCard").style.display = "block";
      }
  
      const allTags = (a.tags || []);
      if (allTags.length) {
        document.getElementById("tagsCard").style.display = "block";
        document.getElementById("articleTagsCloud").innerHTML = allTags
          .map((t) => `<a href="search.html?tag=${encodeURIComponent(t.slug)}">#${escapeHtml(t.name)}</a>`)
          .join("");
      }
  
      wireShareButtons(a.title);
      renderAuthorBox(a.author_profile, a.author);
      renderRelated(a.related || []);
      initParallax();
      if (!isPreview) {
        initComments(a.id);
      } else {
        const commentsSection = document.getElementById("commentsSection");
        if (commentsSection) commentsSection.style.display = "none";
      }
    }
  
    const SOCIAL_ICONS = {
      telegram: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 17-7-6 17-4.5-6.5L3 11Z"/><path d="m9.5 14.5 8.5-9.5"/></svg>',
      whatsapp: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3.5h3l1.5 4-2 2a11 11 0 0 0 5.5 5.5l2-2 4 1.5v3a2 2 0 0 1-2.2 2A17 17 0 0 1 4 6.2 2 2 0 0 1 6 3.5Z"/></svg>',
      instagram: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="3.6"/><circle cx="17.2" cy="6.8" r="1"/></svg>',
      pinterest: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 18c.6-1.7 1.6-5.3 1.6-5.3M12 8.5c2.2 0 3.5 1.4 3.5 3.3 0 2.4-1.3 4.4-3.2 4.4-1 0-1.8-.6-2-1.3"/></svg>',
      x: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4l16 16M20 4 4 20"/></svg>',
      linkedin: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3.5" y="3.5" width="17" height="17" rx="3"/><circle cx="8" cy="9" r="1"/><path d="M8 11.5v5.5M12 17v-3.5c0-1.2 1-2 2-2s2 .8 2 2V17"/></svg>',
      github: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19c-4.3 1.4-4.3-2.5-6-3m12 5v-3.4c0-1 .1-1.4-.5-2 2.8-.3 5.5-1.4 5.5-6a4.6 4.6 0 0 0-1.3-3.2 4.2 4.2 0 0 0-.1-3.2s-1-.3-3.4 1.2a11.8 11.8 0 0 0-6.2 0C6.6 3 5.6 3.3 5.6 3.3a4.2 4.2 0 0 0-.1 3.2A4.6 4.6 0 0 0 4.2 9.7c0 4.6 2.7 5.7 5.5 6-.6.6-.6 1.2-.5 2V21"/></svg>',
      youtube: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="6" width="19" height="12" rx="4"/><path d="m10.5 9.5 5 2.5-5 2.5Z" fill="currentColor" stroke="none"/></svg>',
      website: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.7 4 6 4 9s-1.5 6.3-4 9c-2.5-2.7-4-6-4-9s1.5-6.3 4-9Z"/></svg>',
      email: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3.5" y="5.5" width="17" height="13" rx="2"/><path d="M4.5 7 12 13l7.5-6"/></svg>',
      phone: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3.5h3l1.5 4-2 2a11 11 0 0 0 5.5 5.5l2-2 4 1.5v3a2 2 0 0 1-2.2 2A17 17 0 0 1 4 6.2 2 2 0 0 1 6 3.5Z"/></svg>',
    };
  
    function renderAuthorBox(profile, fallbackName) {
      const box = document.getElementById("authorBox");
      if (!box) return;
      const name = (profile && profile.display_name) || fallbackName;
      if (!name) return;
  
      document.getElementById("authorBoxName").textContent = name;
  
      const avatarImg = document.getElementById("authorBoxAvatar");
      const initialEl = document.getElementById("authorBoxInitial");
      if (profile && profile.avatar) {
        avatarImg.src = profile.avatar;
        avatarImg.style.display = "block";
        initialEl.style.display = "none";
      } else {
        initialEl.textContent = initialsOf(name);
        initialEl.style.display = "flex";
        avatarImg.style.display = "none";
      }
  
      const bioEl = document.getElementById("authorBoxBio");
      if (profile && profile.bio_short) {
        bioEl.textContent = profile.bio_short;
        bioEl.style.display = "block";
      }
  
      const socialsEl = document.getElementById("authorBoxSocials");
      if (profile) {
        const links = (profile.socials || []).map(
          (s) => `<a href="${s.url}" target="_blank" rel="noopener" title="${escapeHtml(s.label)}" class="author-social-btn">${SOCIAL_ICONS[s.key] || SOCIAL_ICONS.website}</a>`
        );
        socialsEl.innerHTML = links.join("");
      }
  
      const linkBtn = document.getElementById("authorBoxLink");
      const avatarLink = document.getElementById("authorBoxAvatarLink");
      const nameLink = document.getElementById("authorBoxNameLink");
      const bioLink = document.getElementById("authorBoxBioLink");
      if (profile && profile.id) {
        const authorUrl = `resume.html?user=${encodeURIComponent(profile.username || "")}`;
        linkBtn.href = authorUrl;
        if (avatarLink) avatarLink.href = authorUrl;
        if (nameLink) nameLink.href = authorUrl;
        if (bioLink) bioLink.href = authorUrl;
      } else {
        linkBtn.style.display = "none";
        if (avatarLink) avatarLink.removeAttribute("href");
        if (nameLink) nameLink.removeAttribute("href");
        if (bioLink) bioLink.removeAttribute("href");
      }
  
      box.style.display = "flex";
    }
  
    function articleCardHtml(article) {
      const img = article.cover_image
        ? `<img src="${article.cover_image}" alt="${escapeHtml(article.title)}" loading="lazy">`
        : "";
      return `
        <article class="article-card">
          <a href="article.html?slug=${encodeURIComponent(article.slug)}" class="article-card-media">${img}</a>
          <div class="article-card-body">
            <h3><a href="article.html?slug=${encodeURIComponent(article.slug)}">${escapeHtml(article.title)}</a></h3>
            <p class="article-card-excerpt">${escapeHtml(article.excerpt || "")}</p>
            <div class="article-card-meta">
              <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M3.5 9.5h17M8 3v3.5M16 3v3.5"/></svg>${formatDate(article.published_at)}</span>
              <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg>${(article.reading_time || 1).toLocaleString("fa-IR")} دقیقه</span>
            </div>
          </div>
        </article>`;
    }
  
    function renderRelated(list) {
      if (!list.length) return;
      document.getElementById("relatedSection").style.display = "block";
      document.getElementById("relatedGrid").innerHTML = list.map(articleCardHtml).join("");
    }
  
    function wireShareButtons(title) {
      const url = window.location.href;
  
      document.getElementById("shareTelegram").href = `https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent(title)}`;
  
      document.getElementById("shareNativeBtn")?.addEventListener("click", async () => {
        if (navigator.share) {
          try {
            await navigator.share({ title, url });
          } catch (e) {
            /* user cancelled */
          }
        } else {
          copyLink(url);
        }
      });
  
      document.getElementById("shareCopyBtn")?.addEventListener("click", () => copyLink(url));
    }
  
    function copyLink(url) {
      navigator.clipboard?.writeText(url).then(() => {
        const btn = document.getElementById("shareCopyBtn");
        const original = btn.innerHTML;
        btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5 9 18l11-12"/></svg>';
        setTimeout(() => (btn.innerHTML = original), 1600);
      });
    }
  
    /* ---------------------------------------------------------
       Reading progress bar
    --------------------------------------------------------- */
    function initReadingProgress() {
      const bar = document.getElementById("readingProgressBar");
      if (!bar) return;
      window.addEventListener(
        "scroll",
        () => {
          const max = document.documentElement.scrollHeight - window.innerHeight;
          const pct = max > 0 ? Math.min(100, (window.scrollY / max) * 100) : 0;
          bar.style.width = pct + "%";
        },
        { passive: true }
      );
    }
  
    /* ---------------------------------------------------------
       Lightweight hero parallax (vanilla, avoids GSAP pin issues on mobile)
    --------------------------------------------------------- */
    function initParallax() {
      const media = document.querySelector(".article-hero-media");
      const hero = document.getElementById("articleHero");
      if (!media || !hero) return;
      const prefersReduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
      if (prefersReduced) return;
  
      let ticking = false;
      function update() {
        const rect = hero.getBoundingClientRect();
        const progress = Math.min(1, Math.max(0, -rect.top / (rect.height || 1)));
        media.style.transform = `translateY(${progress * 8}%)`;
        ticking = false;
      }
      window.addEventListener(
        "scroll",
        () => {
          if (!ticking) {
            requestAnimationFrame(update);
            ticking = true;
          }
        },
        { passive: true }
      );
    }

    /* ---------------------------------------------------------
       Comments — nested list + submit form & MODAL Reply
    --------------------------------------------------------- */
    let currentArticleId = null;
    let openReplyId = null;
    let lastCommentTree = [];
    let lastCommentTotal = 0;

    function refreshCaptchaImg(img) {
      if (img) img.src = "api/captcha.php?for=comment&t=" + Date.now();
    }

    function initComments(articleId) {
      currentArticleId = articleId;
      refreshCaptchaImg(document.getElementById("commentCaptchaImg"));
      document.getElementById("commentCaptchaImg")?.addEventListener("click", (e) => refreshCaptchaImg(e.target));
      
      // فعال‌سازی کلیدها و رویدادهای مودال پاسخ‌دهی پاپ‌آپ
      document.getElementById("closeReplyModalBtn")?.addEventListener("click", closeReplyModal);
      document.getElementById("cancelReplyModalBtn")?.addEventListener("click", closeReplyModal);
      document.getElementById("modalReplyCaptchaImg")?.addEventListener("click", (e) => refreshCaptchaImg(e.target));
      document.getElementById("modalReplyForm")?.addEventListener("submit", handleModalReplySubmit);

      wireCommentForm();
      loadComments(articleId);
    }

    async function loadComments(articleId) {
      const listEl = document.getElementById("commentsList");
      try {
        const res = await fetch("api/comments_list.php?article_id=" + encodeURIComponent(articleId));
        const data = await res.json();
        if (!data.ok) throw new Error(data.message);
        closeReplyModal();
        renderComments(data.data.comments || [], data.data.total_count || 0);
      } catch (err) {
        if (listEl) listEl.innerHTML = '<div class="comments-empty">خطا در بارگذاری نظرات.</div>';
      }
    }

    function commentInitials(name) {
      if (!name) return "ک";
      return name.trim().charAt(0);
    }

    // ساده‌سازی به سبک اینستاگرام: پاسخ به هر پاسخی که باشد، فقط یک پله زیر کامنت اصلی
    // نمایش داده می‌شود (بدون تودرتوی چندلایه). برای این کار همه‌ی پاسخ‌های تو در تو
    // (پاسخ به پاسخ به پاسخ...) را به یک آرایه‌ی مسطح تبدیل می‌کنیم.
    function flattenReplies(node, isRoot) {
      if (isRoot === undefined) isRoot = true;
      let out = [];
      (node.replies || []).forEach((r) => {
        const item = isRoot ? r : Object.assign({}, r, { reply_to_name: node.author_name });
        out.push(item);
        out = out.concat(flattenReplies(r, false));
      });
      return out;
    }

    const COMMENT_VISIBLE_REPLIES = 1; // فقط یک پاسخ نمایش داده می‌شود، بقیه پشت دکمه «نمایش پاسخ‌های بیشتر»

    function replyItemHtml(r) {
      const isAdmin = r.author_type === "admin";
      const avatarInner = isAdmin && r.admin_avatar
        ? `<img src="${r.admin_avatar}" alt="">`
        : `<span>${escapeHtml(commentInitials(r.author_name))}</span>`;

      const nameHtml = isAdmin && r.admin_id
        ? `<a href="search.html?author=${r.admin_id}" class="comment-author-name comment-author-name--admin">${escapeHtml(r.author_name || "ادمین")}</a>`
        : `<span class="comment-author-name">${escapeHtml(r.author_name || "کاربر")}</span>`;

      const adminBadge = isAdmin ? '<span class="comment-admin-badge">نویسنده / ادمین</span>' : "";
      const replyToHtml = r.reply_to_name
        ? `<span class="comment-reply-to">در پاسخ به ${escapeHtml(r.reply_to_name)}</span>`
        : "";

      return `
        <div class="comment-node comment-node--reply" data-comment-id="${r.id}">
          <div class="comment-row">
            <div class="comment-avatar ${isAdmin ? "comment-avatar--admin" : ""}">${avatarInner}</div>
            <div class="comment-body">
              <div class="comment-head">
                ${nameHtml}
                ${adminBadge}
                <span class="comment-date">${formatDate(r.created_at)}</span>
              </div>
              ${replyToHtml}
              <p class="comment-text">${escapeHtml(r.content)}</p>
              <button type="button" class="comment-reply-btn" data-reply-id="${r.id}" data-author-name="${escapeHtml(r.author_name)}">پاسخ</button>
            </div>
          </div>
        </div>`;
    }

    function commentNodeHtml(c) {
      const isAdmin = c.author_type === "admin";
      const avatarInner = isAdmin && c.admin_avatar
        ? `<img src="${c.admin_avatar}" alt="">`
        : `<span>${escapeHtml(commentInitials(c.author_name))}</span>`;

      const nameHtml = isAdmin && c.admin_id
        ? `<a href="search.html?author=${c.admin_id}" class="comment-author-name comment-author-name--admin">${escapeHtml(c.author_name || "ادمین")}</a>`
        : `<span class="comment-author-name">${escapeHtml(c.author_name || "کاربر")}</span>`;

      const adminBadge = isAdmin ? '<span class="comment-admin-badge">نویسنده / ادمین</span>' : "";

      const flatReplies = flattenReplies(c);
      let repliesHtml = "";
      if (flatReplies.length) {
        const visible = flatReplies.slice(0, COMMENT_VISIBLE_REPLIES);
        const hidden = flatReplies.slice(COMMENT_VISIBLE_REPLIES);
        const visibleHtml = visible.map(replyItemHtml).join("");
        repliesHtml = `<div class="comment-replies">${visibleHtml}</div>`;

        if (hidden.length) {
          const hiddenHtml = hidden.map(replyItemHtml).join("");
          const wrapId = `comment-hidden-replies-${c.id}`;
          repliesHtml += `
            <button type="button" class="comment-show-more-btn" data-target="${wrapId}" data-count="${hidden.length}">
              <span class="comment-show-more-label">نمایش ${hidden.length.toLocaleString("fa-IR")} پاسخ بیشتر</span>
            </button>
            <div class="comment-replies comment-replies-hidden" id="${wrapId}">${hiddenHtml}</div>`;
        }
      }

      return `
        <div class="comment-node" data-comment-id="${c.id}">
          <div class="comment-row">
            <div class="comment-avatar ${isAdmin ? "comment-avatar--admin" : ""}">${avatarInner}</div>
            <div class="comment-body">
              <div class="comment-head">
                ${nameHtml}
                ${adminBadge}
                <span class="comment-date">${formatDate(c.created_at)}</span>
              </div>
              <p class="comment-text">${escapeHtml(c.content)}</p>
              <button type="button" class="comment-reply-btn" data-reply-id="${c.id}" data-author-name="${escapeHtml(c.author_name)}">پاسخ</button>
            </div>
          </div>
          ${repliesHtml}
        </div>`;
    }

    function renderComments(tree, totalCount) {
      lastCommentTree = tree;
      lastCommentTotal = totalCount;
      const listEl = document.getElementById("commentsList");
      const countEl = document.getElementById("commentsCount");
      if (countEl) countEl.textContent = totalCount ? `(${totalCount.toLocaleString("fa-IR")})` : "";
      if (!listEl) return;

      if (!tree.length) {
        listEl.innerHTML = '<div class="comments-empty">هنوز نظری ثبت نشده؛ اولین نفری باشید که نظر می‌دهد!</div>';
        return;
      }

      listEl.innerHTML = tree.map(commentNodeHtml).join("");
      wireCommentListEvents(listEl);
    }

    function wireCommentListEvents(listEl) {
      listEl.querySelectorAll("[data-reply-id]").forEach((btn) => {
        btn.addEventListener("click", () => {
          openReplyModal(parseInt(btn.dataset.replyId, 10), btn.dataset.authorName);
        });
      });

      listEl.querySelectorAll(".comment-show-more-btn").forEach((btn) => {
        btn.addEventListener("click", () => {
          const target = document.getElementById(btn.dataset.target);
          if (!target) return;
          const nowVisible = target.classList.toggle("is-visible");
          const label = btn.querySelector(".comment-show-more-label");
          const count = parseInt(btn.dataset.count, 10) || 0;
          if (label) {
            label.textContent = nowVisible
              ? "بستن پاسخ‌ها"
              : `نمایش ${count.toLocaleString("fa-IR")} پاسخ بیشتر`;
          }
        });
      });
    }

    function openReplyModal(commentId, authorName) {
      openReplyId = commentId;
      const modal = document.getElementById("replyModal");
      const title = document.getElementById("modalReplyTitle");
      const form = document.getElementById("modalReplyForm");
      const msgEl = document.getElementById("modalReplyMsg");

      if (!modal || !form) return;

      // ریست کردن داده‌های قبلی فرم مودال
      form.reset();
      if (msgEl) {
        msgEl.textContent = "";
        msgEl.className = "form-msg reply-form-msg";
      }

      title.textContent = `پاسخ به نظر «${authorName || "کاربر"}»`;
      modal.style.display = "flex";
      
      // لود کردن کپچای امنیتی منحصربه‌فرد برای مودال پاسخ
      refreshCaptchaImg(document.getElementById("modalReplyCaptchaImg"));
      document.getElementById("modalReplyName")?.focus();
    }

    function closeReplyModal() {
      openReplyId = null;
      const modal = document.getElementById("replyModal");
      if (modal) modal.style.display = "none";
    }

    async function handleModalReplySubmit(e) {
      e.preventDefault();
      const form = e.currentTarget;
      const msgEl = document.getElementById("modalReplyMsg");
      msgEl.textContent = "";
      msgEl.className = "form-msg reply-form-msg";
      const submitBtn = document.getElementById("modalReplySubmitBtn");
      submitBtn.disabled = true;

      const fd = new FormData();
      fd.append("article_id", currentArticleId);
      fd.append("parent_id", openReplyId);
      fd.append("user_name", document.getElementById("modalReplyName").value.trim());
      fd.append("content", document.getElementById("modalReplyContent").value.trim());
      fd.append("captcha", document.getElementById("modalReplyCaptchaInput").value.trim());
      fd.append("website", form.website.value);

      try {
        const res = await fetch("api/comments_submit.php", { method: "POST", body: fd });
        const data = await res.json();
        if (!data.ok) {
          const firstError = data.errors ? Object.values(data.errors)[0] : null;
          throw new Error(firstError || data.message || "خطا در ثبت پاسخ.");
        }
        
        if (data.data && data.data.auto_approved) {
          closeReplyModal();
          loadComments(currentArticleId);
        } else {
          msgEl.textContent = data.message;
          msgEl.classList.add("is-success");
          refreshCaptchaImg(document.getElementById("modalReplyCaptchaImg"));
          document.getElementById("modalReplyCaptchaInput").value = "";
          setTimeout(closeReplyModal, 2000);
        }
      } catch (err) {
        msgEl.textContent = err.message || "خطا در ثبت پاسخ.";
        msgEl.classList.add("is-error");
        refreshCaptchaImg(document.getElementById("modalReplyCaptchaImg"));
        document.getElementById("modalReplyCaptchaInput").value = "";
      } finally {
        submitBtn.disabled = false;
      }
    }

    function wireCommentForm() {
      const form = document.getElementById("commentForm");
      if (!form || form.dataset.commentsWired === "1") return;
      form.dataset.commentsWired = "1";

      form.addEventListener("submit", async (e) => {
        e.preventDefault();
        const msgEl = document.getElementById("commentFormMsg");
        msgEl.textContent = "";
        msgEl.className = "form-msg";
        const submitBtn = document.getElementById("commentSubmitBtn");
        submitBtn.disabled = true;

        const fd = new FormData();
        fd.append("article_id", currentArticleId);
        fd.append("parent_id", "");
        fd.append("user_name", document.getElementById("commentName").value.trim());
        fd.append("content", document.getElementById("commentContent").value.trim());
        fd.append("captcha", document.getElementById("commentCaptcha").value.trim());
        fd.append("website", form.website.value);

        try {
          const res = await fetch("api/comments_submit.php", { method: "POST", body: fd });
          const data = await res.json();
          if (!data.ok) {
            const firstError = data.errors ? Object.values(data.errors)[0] : null;
            throw new Error(firstError || data.message || "خطا در ثبت نظر.");
          }
          msgEl.textContent = data.message;
          msgEl.classList.add("is-success");
          document.getElementById("commentContent").value = "";
          document.getElementById("commentCaptcha").value = "";
          refreshCaptchaImg(document.getElementById("commentCaptchaImg"));
          if (data.data && data.data.auto_approved) {
            loadComments(currentArticleId);
          }
        } catch (err) {
          msgEl.textContent = err.message || "خطا در ثبت نظر.";
          msgEl.classList.add("is-error");
          refreshCaptchaImg(document.getElementById("commentCaptchaImg"));
          document.getElementById("commentCaptcha").value = "";
        } finally {
          submitBtn.disabled = false;
        }
      });
    }
  })();