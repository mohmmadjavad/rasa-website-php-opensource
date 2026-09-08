/* ===========================================================
   team-section.js — کارت‌های «تیم ما» به‌صورت داینامیک
   -----------------------------------------------------------
   هم در index.html و هم در about.html included می‌شود تا هر دو
   صفحه دقیقاً از یک منبع (api/team_public.php) و یک منطق رندر
   استفاده کنند و کارت‌ها همیشه یکسان باشند.

   استایل خودِ کارت دقیقاً مثل قبل (فقط عکس پس‌زمینه) است؛
   همه‌ی اطلاعات (بیو، عنوان شغلی، شبکه‌ها، دکمه پروفایل) داخل
   مودال (initTeamModalController در js/index.js) نمایش داده می‌شود.

   GET api/team_public.php -> { ok:true, data:{ team:[
       { name, job_title, card_image, avatar, bio_short,
         socials:[{key,label,value,url}], profile_url|null } ] } }
   =========================================================== */
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", loadTeamSection);

  function escapeHtml(str) {
    return String(str ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function teamCardHtml(member) {
    const socialsJson = escapeHtml(JSON.stringify(member.socials || []));
    const bg = member.card_image || member.avatar || "";
    return `
      <div class="team-card" style="background-image:url('${escapeHtml(bg)}'); background-size:cover; background-position:center; cursor:pointer;"
           data-name="${escapeHtml(member.name || "")}"
           data-job="${escapeHtml(member.job_title || "")}"
           data-img="${escapeHtml(member.avatar || member.card_image || "")}"
           data-bio="${escapeHtml(member.bio_short || "")}"
           data-socials="${socialsJson}"
           data-profile="${escapeHtml(member.profile_url || "")}">
      </div>`;
  }

  async function loadTeamSection() {
    const track = document.getElementById("teamTrack");
    const dotsWrap = document.getElementById("teamDots");
    if (!track) return;

    try {
      const res = await fetch("api/team_public.php");
      const data = await res.json();
      const team = (data && data.ok && data.data && data.data.team) || [];

      if (!team.length) {
        document.getElementById("team")?.style.setProperty("display", "none");
        return;
      }

      track.innerHTML = team.map(teamCardHtml).join("");
      if (dotsWrap) {
        dotsWrap.innerHTML = team
          .map((_, i) => `<button class="team-dot${i === 0 ? " is-active" : ""}" type="button" aria-label="عضو شماره ${i + 1}"></button>`)
          .join("");
      }
    } catch (e) {
      console.error("[team-section.js] خطا در دریافت اطلاعات تیم:", e);
      return;
    }

    if (typeof window.initTeamCarousel === "function") window.initTeamCarousel();
    if (typeof window.initTeamModalController === "function") window.initTeamModalController();
  }
})();
