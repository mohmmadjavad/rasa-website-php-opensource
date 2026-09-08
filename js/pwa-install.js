/**
 * js/pwa-install.js
 * فقط در articles.html بارگذاری می‌شود.
 * - سرویس‌ورکر را ثبت می‌کند (لازم برای نصب‌پذیری PWA)
 * - رویداد beforeinstallprompt مرورگر را می‌گیرد و به‌جای نوار پیش‌فرض
 *   مرورگر، یک نوتیف سفارشی (مستطیل گوشه‌گرد بالای صفحه) بعد از ۳۰ ثانیه
 *   حضور کاربر در صفحه نشان می‌دهد. با زدن دکمه «نصب»، پرامپت واقعی نصب
 *   مرورگر باز می‌شود.
 */
(function () {
  "use strict";

  var DISMISS_KEY = "resa_pwa_install_dismissed_at";
  var DISMISS_DAYS = 7;
  var SHOW_DELAY_MS = 30000;

  // ---------- ثبت سرویس‌ورکر ----------
  if ("serviceWorker" in navigator) {
    window.addEventListener("load", function () {
      navigator.serviceWorker.register("/service-worker.js").catch(function () {
        /* اگر ثبت سرویس‌ورکر شکست بخورد، سایت همچنان عادی کار می‌کند */
      });
    });
  }

  // اگر کاربر همین الان اپ را به‌صورت نصب‌شده باز کرده، نیازی به نوتیف نصب نیست
  var isStandalone =
    window.matchMedia("(display-mode: standalone)").matches ||
    window.navigator.standalone === true;
  if (isStandalone) return;

  // اگر اخیراً بسته شده، دوباره نمایش نده
  function wasRecentlyDismissed() {
    var raw = localStorage.getItem(DISMISS_KEY);
    if (!raw) return false;
    var elapsedDays = (Date.now() - parseInt(raw, 10)) / (1000 * 60 * 60 * 24);
    return elapsedDays < DISMISS_DAYS;
  }

  var deferredPrompt = null;

  window.addEventListener("beforeinstallprompt", function (e) {
    e.preventDefault();
    deferredPrompt = e;

    if (wasRecentlyDismissed()) return;

    setTimeout(function () {
      if (!deferredPrompt) return; // کاربر شاید تا آن موقع خودش نصب کرده باشد
      showInstallToast();
    }, SHOW_DELAY_MS);
  });

  window.addEventListener("appinstalled", function () {
    hideInstallToast();
    deferredPrompt = null;
  });

  function showInstallToast() {
    var toast = document.getElementById("pwaInstallToast");
    if (!toast) return;
    toast.hidden = false;
    requestAnimationFrame(function () {
      toast.classList.add("is-visible");
    });
  }

  function hideInstallToast() {
    var toast = document.getElementById("pwaInstallToast");
    if (!toast) return;
    toast.classList.remove("is-visible");
    setTimeout(function () {
      toast.hidden = true;
    }, 300);
  }

  document.addEventListener("DOMContentLoaded", function () {
    var installBtn = document.getElementById("pwaInstallBtn");
    var closeBtn = document.getElementById("pwaInstallClose");

    if (installBtn) {
      installBtn.addEventListener("click", function () {
        hideInstallToast();
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        deferredPrompt.userChoice.finally(function () {
          deferredPrompt = null;
        });
      });
    }

    if (closeBtn) {
      closeBtn.addEventListener("click", function () {
        hideInstallToast();
        localStorage.setItem(DISMISS_KEY, String(Date.now()));
      });
    }
  });
})();
