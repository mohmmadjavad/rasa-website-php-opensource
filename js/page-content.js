/**
 * js/page-content.js
 * محتوای صفحه‌های «درباره ما» و «تماس با ما» (متن‌ها، اعضای تیم، جوایز) را از پنل ادمین می‌خواند
 * و به‌صورت پویا در صفحه جایگزین می‌کند. اگر واکشی با خطا مواجه شود، محتوای پیش‌فرض صفحه دست‌نخورده می‌ماند.
 */
(function () {
  function escapeHtml(str) {
    const div = document.createElement("div");
    div.textContent = str ?? "";
    return div.innerHTML;
  }

  function setText(id, value) {
    const el = document.getElementById(id);
    if (el && value) el.textContent = value;
  }

  function applyAbout(content, awards) {
    setText("aboutEyebrow", content.hero_eyebrow);
    setText("aboutHeroTitle", content.hero_title);
    setText("aboutHeroSub", content.hero_sub);
    setText("storyIntroTitle", content.story_title);

    if (content.story_body) {
      const wrap = document.getElementById("storyBodyContent");
      if (wrap) {
        wrap.querySelectorAll(".story-paragraph").forEach((p) => p.remove());
        const paragraphs = String(content.story_body)
          .split(/\n\s*\n/)
          .map((p) => p.trim())
          .filter(Boolean);
        paragraphs.forEach((text) => {
          const p = document.createElement("p");
          p.className = "story-paragraph";
          p.textContent = text;
          wrap.appendChild(p);
        });
      }
    }

    const track = document.getElementById("awardsTrack");
    if (track) {
      const list = Array.isArray(awards) ? awards : [];
      track.innerHTML = list
        .map(
          (a, i) =>
            `<div class="award-item" data-index="${i + 1}"><img src="${escapeHtml(a.image || "")}" alt="${escapeHtml(a.title || "افتخار " + (i + 1))}"></div>`
        )
        .join("");
      if (list.length && typeof window.initAwards3DCarousel === "function") window.initAwards3DCarousel();
    }
  }

  function applyContact(content) {
    setText("contactHeroTitleEl", content.hero_title);
    setText("contactHeroSubEl", content.hero_sub);

    if (content.address) {
      const el = document.getElementById("contactAddressValue");
      if (el) el.innerHTML = escapeHtml(content.address).replace(/\n/g, "<br>");
    }
    if (content.phone) {
      const el = document.getElementById("contactPhoneValue");
      if (el) {
        el.textContent = content.phone;
        el.setAttribute("href", "tel:" + content.phone.replace(/\s+/g, ""));
      }
    }
    if (content.email) {
      const el = document.getElementById("contactEmailValue");
      if (el) {
        el.textContent = content.email;
        el.setAttribute("href", "mailto:" + content.email);
      }
    }
    if (content.hours_days || content.hours_time) {
      const el = document.getElementById("contactHoursValue");
      if (el) el.innerHTML = [content.hours_days, content.hours_time].filter(Boolean).map(escapeHtml).join("<br>");
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    const isContact = document.body.classList.contains("contact-body");
    const isAbout = document.body.classList.contains("about-body");
    const page = isContact ? "contact" : isAbout ? "about" : "home";

    fetch("api/page_content.php?page=" + page)
      .then((res) => res.json())
      .then((data) => {
        if (!data || !data.ok || !data.data) return;
        const d = data.data;
        if (page === "contact") {
          applyContact(d.content || {});
        } else {
          applyAbout(d.content || {}, d.awards || []);
        }
      })
      .catch(() => {
        /* در صورت خطا، محتوای پیش‌فرض صفحه دست‌نخورده باقی می‌ماند */
      });
  });
})();
