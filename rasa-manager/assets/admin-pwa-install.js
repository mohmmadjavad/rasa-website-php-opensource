/**
 * assets/admin-pwa-install.js
 * فقط در پنل ادمین (index.php) بارگذاری می‌شود.
 * - سرویس‌ورکر مخصوص پنل ادمین را ثبت می‌کند (لازم برای نصب‌پذیری PWA)
 * - ردیف «نصب برنامه پنل ادمین» در بالای تب تنظیمات را مدیریت می‌کند:
 *   با گرفتن رویداد beforeinstallprompt مرورگر، با کلیک روی این ردیف
 *   پرامپت واقعی نصب مرورگر باز می‌شود.
 */
(function () {
  "use strict";

  // ---------- ثبت سرویس‌ورکر مخصوص پنل ادمین ----------
  if ("serviceWorker" in navigator) {
    window.addEventListener("load", function () {
      navigator.serviceWorker.register("service-worker-admin.js", { scope: "./" }).catch(function () {
        /* اگر ثبت سرویس‌ورکر شکست بخورد، پنل همچنان عادی کار می‌کند */
      });
    });
  }

  var isStandalone =
    window.matchMedia("(display-mode: standalone)").matches ||
    window.navigator.standalone === true;

  var isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent || "");

  var deferredPrompt = null;

  document.addEventListener("DOMContentLoaded", function () {
    var row = document.getElementById("installAdminAppRow");
    var subEl = document.getElementById("installAdminAppSub");
    var chevron = document.getElementById("installAdminAppChevron");
    if (!row) return;

    function setSub(text) {
      if (subEl) subEl.textContent = text;
    }

    function markInstalled() {
      row.classList.add("is-disabled");
      row.disabled = true;
      if (chevron) chevron.textContent = "✓";
      setSub("برنامه پنل ادمین نصب شده است");
    }

    if (isStandalone) {
      markInstalled();
      return;
    }

    row.addEventListener("click", function () {
      if (deferredPrompt) {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.finally(function () {
          deferredPrompt = null;
        });
        return;
      }

      if (isIOS) {
        setSub("در سافاری: دکمه اشتراک‌گذاری را بزنید و «افزودن به صفحه اصلی» را انتخاب کنید");
        return;
      }

      setSub("مرورگر شما امکان نصب مستقیم را پشتیبانی نمی‌کند");
    });

    window.addEventListener("beforeinstallprompt", function (e) {
      e.preventDefault();
      deferredPrompt = e;
      setSub("برای دسترسی سریع‌تر، پنل ادمین را به‌صورت اپلیکیشن نصب کنید");
    });

    window.addEventListener("appinstalled", function () {
      deferredPrompt = null;
      markInstalled();
    });
  });
})();
