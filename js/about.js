/* ===========================================================
   about.js
   اسکریپت اختصاصی صفحه‌ی درباره ما: نمایان‌شدن تدریجی آیتم‌های
   تایم‌لاین هنگام اسکرول. بقیه‌ی تعامل‌های این صفحه (کاروسل تیم،
   مودال تیم، کاروسل افتخارات) عیناً از js/index.js می‌آید.
   =========================================================== */
document.addEventListener("DOMContentLoaded", () => {
  const items = document.querySelectorAll(".about-timeline-item");
  if (items.length) {
    if (typeof IntersectionObserver === "undefined") {
      items.forEach((el) => el.classList.add("is-visible"));
    } else {
      const observer = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              entry.target.classList.add("is-visible");
              observer.unobserve(entry.target);
            }
          });
        },
        { threshold: 0.2, rootMargin: "0px 0px -8% 0px" }
      );
      items.forEach((el) => observer.observe(el));
    }
  }

  initStatsCounters();
  loadAboutClientBrands();
});

/* ---------------------------------------------------------
   برندهایی که با رسا کار کرده‌اند — از دیتابیس (بخش پروژه‌ها در پنل ادمین)
--------------------------------------------------------- */
async function loadAboutClientBrands() {
  const strip = document.getElementById("aboutClientsStrip");
  if (!strip) return;

  function escapeHtml(str) {
    const div = document.createElement("div");
    div.textContent = str ?? "";
    return div.innerHTML;
  }

  try {
    const res = await fetch("api/brands_public.php");
    const data = await res.json();
    if (!data.ok) throw new Error(data.message || "bad response");
    const brands = data.data.brands || [];

    if (!brands.length) {
      document.getElementById("aboutClientsSection")?.style.setProperty("display", "none");
      return;
    }

    strip.innerHTML = brands
      .map((b) => {
        const label = escapeHtml(b.name);
        const inner = b.logo_image
          ? `<img src="${b.logo_image}" alt="${label}" loading="lazy">`
          : label;
        return `<a class="about-client-chip ${b.logo_image ? "about-client-chip--logo" : ""}" href="projects.html?brand=${encodeURIComponent(b.slug)}" title="${label}">${inner}</a>`;
      })
      .join("");

    const sub = document.getElementById("aboutClientsSub");
    if (sub) sub.style.display = "none";
  } catch (e) {
    console.error("[about.js] خطا در دریافت لیست برندها:", e);
    document.getElementById("aboutClientsSection")?.style.setProperty("display", "none");
  }
}

/* ---------------------------------------------------------
   شمارنده‌های بخش «آمار رسا» — از صفر تا عدد نهایی هنگام ورود به دید
--------------------------------------------------------- */
function initStatsCounters() {
  const nums = document.querySelectorAll(".about-stat-num");
  if (!nums.length) return;

  function toFa(n) { return n.toLocaleString("fa-IR"); }

  function animate(el) {
    const target = parseInt(el.dataset.count, 10) || 0;
    const suffix = el.dataset.suffix || "";
    const duration = 1400;
    const start = performance.now();

    function tick(now) {
      const progress = Math.min(1, (now - start) / duration);
      const eased = 1 - Math.pow(1 - progress, 3);
      el.textContent = toFa(Math.round(target * eased)) + suffix;
      if (progress < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }

  if (typeof IntersectionObserver === "undefined") {
    nums.forEach(animate);
    return;
  }

  const statsObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          animate(entry.target);
          statsObserver.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.4 }
  );
  nums.forEach((el) => statsObserver.observe(el));
}
