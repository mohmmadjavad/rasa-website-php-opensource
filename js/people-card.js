/* ===========================================================
   js/people-card.js — رندر یکپارچه‌ی «کارت افراد»
   -----------------------------------------------------------
   یک قالب کارت مشترک برای: کارت‌های «تیم ما» (index.html/about.html)
   و کارت‌های «افراد توسعه دهنده» (contact.html). طرح: بنر رنگی +
   آواتار مستطیلی گوشه‌گرد شناور روی مرز بنر + نام/سمت + بیوی کوتاه +
   ردیف شبکه‌های اجتماعی (آیکون + اسم زیرش) + دکمه «مشاهده موارد بیشتر».
   ورودی هر آیتم: { name, job_title, bio_short, avatar, card_image,
                     socials:[{key,label,url}], profile_url }
   =========================================================== */
(function () {
  "use strict";

  var SOCIAL_SVG = {
    telegram: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21 4-9 16-3-7-7-3Z"/><path d="M21 4 8.5 13.2"/></svg>',
    whatsapp: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3.5h3l1.5 4-2 2a11 11 0 0 0 5.5 5.5l2-2 4 1.5v3a2 2 0 0 1-2.2 2A17 17 0 0 1 4 6.2 2 2 0 0 1 6 3.5Z"/></svg>',
    instagram: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/></svg>',
    pinterest: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 18c.6-1.7 1.6-5.3 1.6-5.3M12 8.5c2.2 0 3.5 1.4 3.5 3.3 0 2.4-1.3 4.4-3.2 4.4-1 0-1.8-.6-2-1.3"/></svg>',
    x: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m4 4 16 16M20 4 4 20"/></svg>',
    linkedin: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3.5" y="3.5" width="17" height="17" rx="3"/><circle cx="8" cy="9" r="1"/><path d="M8 11.5v5.5M12 17v-3.5c0-1.2 1-2 2-2s2 .8 2 2V17"/></svg>',
    github: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19c-4.3 1.4-4.3-2.5-6-3m12 5v-3.4c0-1 .1-1.4-.5-2 2.8-.3 5.5-1.4 5.5-6a4.6 4.6 0 0 0-1.3-3.2 4.2 4.2 0 0 0-.1-3.2s-1-.3-3.4 1.2a11.8 11.8 0 0 0-6.2 0C6.6 3 5.6 3.3 5.6 3.3a4.2 4.2 0 0 0-.1 3.2A4.6 4.6 0 0 0 4.2 9.7c0 4.6 2.7 5.7 5.5 6-.6.6-.6 1.2-.5 2V21"/></svg>',
    youtube: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="6" width="19" height="12" rx="4"/><path d="m10.5 9.5 5 2.5-5 2.5Z" fill="currentColor" stroke="none"/></svg>',
    website: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.7 4 6 4 9s-1.5 6.3-4 9c-2.5-2.7-4-6-4-9s1.5-6.3 4-9Z"/></svg>',
    email: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3.5" y="5.5" width="17" height="13" rx="2"/><path d="M4.5 7 12 13l7.5-6"/></svg>',
    phone: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3.5h3l1.5 4-2 2a11 11 0 0 0 5.5 5.5l2-2 4 1.5v3a2 2 0 0 1-2.2 2A17 17 0 0 1 4 6.2 2 2 0 0 1 6 3.5Z"/></svg>',
    custom: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.7 4 6 4 9s-1.5 6.3-4 9c-2.5-2.7-4-6-4-9s1.5-6.3 4-9Z"/></svg>'
  };

  function escapeHtml(str) {
    var div = document.createElement("div");
    div.textContent = str ?? "";
    return div.innerHTML;
  }

  function render(person, opts) {
    opts = opts || {};
    var banner = person.card_image || person.avatar || "";
    var avatarImg = person.avatar || person.card_image || "";

    var socialsHtml = (person.socials || [])
      .map(function (s) {
        return (
          '<a href="' + escapeHtml(s.url) + '" target="_blank" rel="noopener noreferrer" class="pc-social-item" onclick="event.stopPropagation()" aria-label="' +
          escapeHtml(s.label) + " " + escapeHtml(person.name || "") + '">' +
          '<span class="pc-social-icon">' + (SOCIAL_SVG[s.key] || SOCIAL_SVG.website) + "</span>" +
          '<span class="pc-social-label">' + escapeHtml(s.label) + "</span>" +
          "</a>"
        );
      })
      .join("");

    var moreBtnHtml = person.profile_url
      ? '<a class="pc-more-btn" href="' + escapeHtml(person.profile_url) + '" onclick="event.stopPropagation()">' +
        "مشاهده موارد بیشتر" +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>' +
        "</a>"
      : "";

    return (
      '<div class="people-card">' +
        '<div class="pc-banner" style="background-image:url(\'' + escapeHtml(banner) + '\')">' +
          '<div class="pc-avatar-frame">' +
            (avatarImg ? '<img src="' + escapeHtml(avatarImg) + '" alt="' + escapeHtml(person.name || "") + '" loading="lazy">' : "") +
          "</div>" +
        "</div>" +
        '<div class="pc-body">' +
          '<div class="pc-name">' + escapeHtml(person.name || "") + "</div>" +
          (person.job_title ? '<div class="pc-job">' + escapeHtml(person.job_title) + "</div>" : "") +
          (person.bio_short ? '<p class="pc-bio">' + escapeHtml(person.bio_short) + "</p>" : "") +
          '<div class="pc-socials">' + socialsHtml + "</div>" +
          moreBtnHtml +
        "</div>" +
      "</div>"
    );
  }

  window.ResaPeopleCard = { render: render };
})();
