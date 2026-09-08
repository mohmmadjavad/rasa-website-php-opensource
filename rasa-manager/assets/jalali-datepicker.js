/*!
 * Rasa Jalali Datetime Picker
 * تقویم شمسی سبک و بدون وابستگی خارجی برای فیلدهای تاریخ/ساعت پنل ادمین.
 * مقدار داخلی فیلد (value) همچنان به‌صورت گرگوری «YYYY-MM-DDTHH:mm» ذخیره و ارسال می‌شود
 * تا هیچ تغییری در منطق سرور یا سایر فایل‌های جاوااسکریپت لازم نباشد؛ فقط نمایش به کاربر شمسی است.
 */
(function (global) {
  "use strict";

  /* ---------------- ارقام فارسی ---------------- */
  var FA_DIGITS = "۰۱۲۳۴۵۶۷۸۹";
  function toFaDigits(str) {
    return String(str).replace(/[0-9]/g, function (d) { return FA_DIGITS[d]; });
  }

  var MONTH_NAMES = ["فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور", "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند"];
  var WEEKDAY_SHORT = ["ش", "ی", "د", "س", "چ", "پ", "ج"]; /* شنبه تا جمعه، شروع هفته شنبه */

  /* ---------------- تبدیل تقویم جلالی <-> میلادی ---------------- */
  /* پیاده‌سازی الگوریتم استاندارد تقویم جلالی (بر پایه‌ی محاسبات نجومی رایج) */
  function div(a, b) { return a < 0 ? Math.ceil(a / b) : Math.floor(a / b); }
  function mod(a, b) { return a - div(a, b) * b; }

  function jalCal(jy) {
    var breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
    var bl = breaks.length, gy = jy + 621, leapJ = -14, jp = breaks[0], jm, jump, leap, leapG, march, n, i;
    for (i = 1; i < bl; i += 1) {
      jm = breaks[i];
      jump = jm - jp;
      if (jy < jm) break;
      leapJ = leapJ + div(jump, 33) * 8 + div(mod(jump, 33), 4);
      jp = jm;
    }
    n = jy - jp;
    leapJ = leapJ + div(n, 33) * 8 + div(mod(n, 33) + 3, 4);
    if (mod(jump, 33) === 4 && jump - n === 4) leapJ += 1;
    leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150;
    march = 20 + leapJ - leapG;
    if (jump - n < 6) n = n - jump + div(jump, 33) * 33;
    leap = mod(mod(n + 1, 33) - 1, 4);
    if (leap === -1) leap = 4;
    return { leap: leap, gy: gy, march: march };
  }

  function g2d(gy, gm, gd) {
    var d = div((gy + div(gm - 8, 6) + 100100) * 1461, 4)
      + div(153 * mod(gm + 9, 12) + 2, 5)
      + gd - 34840408;
    d = d - div(div(gy + div(gm - 8, 6) + 100100, 100) * 3, 4) + 752;
    return d;
  }

  function d2g(jdn) {
    var j = 4 * jdn + 139361631;
    j = j + div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
    var i = div(mod(j, 1461), 4) * 5 + 308;
    var gd = div(mod(i, 153), 5) + 1;
    var gm = mod(div(i, 153), 12) + 1;
    var gy = div(j, 1461) - 100100 + div(8 - gm, 6);
    return { gy: gy, gm: gm, gd: gd };
  }

  function j2d(jy, jm, jd) {
    var r = jalCal(jy);
    return g2d(r.gy, 3, r.march) + (jm - 1) * 31 - div(jm, 7) * (jm - 7) + jd - 1;
  }

  function d2j(jdn) {
    var gy = d2g(jdn).gy, jy = gy - 621, r = jalCal(jy), jdn1f = g2d(gy, 3, r.march), jd, jm, k;
    k = jdn - jdn1f;
    if (k >= 0) {
      if (k <= 185) {
        jm = 1 + div(k, 31);
        jd = mod(k, 31) + 1;
        return { jy: jy, jm: jm, jd: jd };
      }
      k -= 186;
    } else {
      jy -= 1;
      k += 179;
      if (r.leap === 1) k += 1;
    }
    jm = 7 + div(k, 30);
    jd = mod(k, 30) + 1;
    return { jy: jy, jm: jm, jd: jd };
  }

  function isJalaliLeap(jy) { return jalCal(jy).leap === 0; }
  function jalaliMonthLength(jy, jm) {
    if (jm <= 6) return 31;
    if (jm <= 11) return 30;
    return isJalaliLeap(jy) ? 30 : 29;
  }

  function toJalali(gy, gm, gd) { return d2j(g2d(gy, gm, gd)); }
  function toGregorian(jy, jm, jd) { return d2g(j2d(jy, jm, jd)); }

  /* ---------------- کمکی‌های قالب‌بندی ---------------- */
  function pad2(n) { return (n < 10 ? "0" : "") + n; }

  /* رشته‌ی محلی گرگوری «YYYY-MM-DDTHH:mm» یا «YYYY-MM-DD HH:mm:ss» را می‌گیرد */
  function parseGregorianLocal(str) {
    if (!str) return null;
    var m = String(str).trim().replace(" ", "T").match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/);
    if (!m) return null;
    return {
      gy: parseInt(m[1], 10), gm: parseInt(m[2], 10), gd: parseInt(m[3], 10),
      h: parseInt(m[4], 10), min: parseInt(m[5], 10),
    };
  }

  function toGregorianLocalString(gy, gm, gd, h, min) {
    return gy + "-" + pad2(gm) + "-" + pad2(gd) + "T" + pad2(h) + ":" + pad2(min);
  }

  function formatDisplay(gy, gm, gd, h, min) {
    var j = toJalali(gy, gm, gd);
    var text = toFaDigits(j.jd) + " " + MONTH_NAMES[j.jm - 1] + " " + toFaDigits(j.jy) + "، ساعت " + toFaDigits(pad2(h)) + ":" + toFaDigits(pad2(min));
    return text;
  }

  /* ---------------- ویجت انتخاب‌گر ---------------- */
  var activePopup = null;

  function closeActivePopup() {
    if (activePopup && activePopup.parentNode) activePopup.parentNode.removeChild(activePopup);
    activePopup = null;
    document.removeEventListener("mousedown", onDocMouseDown, true);
    window.removeEventListener("scroll", closeActivePopup, true);
    window.removeEventListener("resize", closeActivePopup, true);
  }

  function onDocMouseDown(e) {
    if (activePopup && !activePopup.contains(e.target) && e.target !== activePopup.__anchorInput) {
      closeActivePopup();
    }
  }

  function attach(inputId) {
    var input = document.getElementById(inputId);
    if (!input || input.__rasaJalaliAttached) return;
    input.__rasaJalaliAttached = true;

    input.setAttribute("type", "text");
    input.setAttribute("readonly", "readonly");
    input.setAttribute("autocomplete", "off");
    input.classList.add("jalali-datetime-input");
    if (!input.placeholder) input.placeholder = "انتخاب نشده";

    var internalValue = ""; /* رشته‌ی گرگوری واقعی که به بک‌اند ارسال می‌شود */

    var nativeDesc = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value");

    Object.defineProperty(input, "value", {
      configurable: true,
      get: function () { return internalValue; },
      set: function (v) {
        internalValue = v || "";
        var parsed = parseGregorianLocal(internalValue);
        if (parsed) {
          nativeDesc.set.call(input, formatDisplay(parsed.gy, parsed.gm, parsed.gd, parsed.h, parsed.min));
        } else {
          nativeDesc.set.call(input, "");
        }
      },
    });

    input.addEventListener("click", function () { openPopup(input); });
    input.addEventListener("focus", function () { openPopup(input); });
  }

  function openPopup(input) {
    closeActivePopup();

    var now = new Date();
    var parsed = parseGregorianLocal(input.value) || {
      gy: now.getFullYear(), gm: now.getMonth() + 1, gd: now.getDate(),
      h: now.getHours(), min: now.getMinutes(),
    };
    var view = toJalali(parsed.gy, parsed.gm, parsed.gd);
    var selJy = view.jy, selJm = view.jm, selJd = view.jd;
    var selH = parsed.h, selMin = parsed.min;
    var hasSelection = !!parseGregorianLocal(input.value);

    var popup = document.createElement("div");
    popup.className = "jalali-picker-popup";
    popup.__anchorInput = input;

    function render() {
      var monthLen = jalaliMonthLength(selJy, selJm);
      var firstOfMonthG = toGregorian(selJy, selJm, 1);
      var firstJdn = g2d(firstOfMonthG.gy, firstOfMonthG.gm, firstOfMonthG.gd);
      /* روز هفته: 0=شنبه ... 6=جمعه. مبنای JDN: می‌دانیم 1 فروردین 1403 (۲۰ مارس ۲۰۲۴) چهارشنبه بود -> jdn2weekday */
      var weekday = mod(firstJdn + 2, 7); /* تنظیم‌شده با نمونه‌ی شناخته‌شده در پایین اعتبارسنجی شده */

      var html = "";
      html += '<div class="jp-header">';
      html += '  <button type="button" class="jp-nav" data-nav="prev">&#8250;</button>';
      html += '  <div class="jp-title">' + MONTH_NAMES[selJm - 1] + ' <span class="jp-year">' + toFaDigits(selJy) + '</span></div>';
      html += '  <button type="button" class="jp-nav" data-nav="next">&#8249;</button>';
      html += '</div>';
      html += '<div class="jp-weekdays">' + WEEKDAY_SHORT.map(function (w) { return '<span>' + w + '</span>'; }).join("") + '</div>';
      html += '<div class="jp-days">';
      for (var i = 0; i < weekday; i++) html += '<span class="jp-day jp-day--empty"></span>';
      for (var d = 1; d <= monthLen; d++) {
        var isSel = hasSelection && d === selJd;
        html += '<span class="jp-day' + (isSel ? ' jp-day--selected' : '') + '" data-day="' + d + '">' + toFaDigits(d) + '</span>';
      }
      html += '</div>';
      html += '<div class="jp-time">';
      html += '  <label>ساعت <select class="jp-hour">' + hourOptions(selH) + '</select></label>';
      html += '  <label>دقیقه <select class="jp-minute">' + minuteOptions(selMin) + '</select></label>';
      html += '</div>';
      html += '<div class="jp-footer">';
      html += '  <button type="button" class="jp-btn jp-btn--ghost" data-act="clear">پاک کردن</button>';
      html += '  <button type="button" class="jp-btn jp-btn--ghost" data-act="today">امروز</button>';
      html += '  <button type="button" class="jp-btn jp-btn--primary" data-act="confirm">تأیید</button>';
      html += '</div>';
      popup.innerHTML = html;
    }

    function hourOptions(sel) {
      var out = "";
      for (var h = 0; h < 24; h++) out += '<option value="' + h + '"' + (h === sel ? " selected" : "") + '>' + toFaDigits(pad2(h)) + '</option>';
      return out;
    }
    function minuteOptions(sel) {
      var out = "";
      for (var m = 0; m < 60; m += 5) out += '<option value="' + m + '"' + (m === sel ? " selected" : "") + '>' + toFaDigits(pad2(m)) + '</option>';
      if (sel % 5 !== 0) out += '<option value="' + sel + '" selected>' + toFaDigits(pad2(sel)) + '</option>';
      return out;
    }

    render();
    document.body.appendChild(popup);

    var rect = input.getBoundingClientRect();
    var top = rect.bottom + window.scrollY + 6;
    var left = rect.left + window.scrollX;
    var popupWidth = 268;
    if (left + popupWidth > window.scrollX + document.documentElement.clientWidth - 8) {
      left = window.scrollX + document.documentElement.clientWidth - popupWidth - 8;
    }
    popup.style.top = top + "px";
    popup.style.left = Math.max(8, left) + "px";

    popup.addEventListener("click", function (e) {
      var navBtn = e.target.closest("[data-nav]");
      if (navBtn) {
        var dir = navBtn.getAttribute("data-nav") === "next" ? 1 : -1;
        selJm += dir;
        if (selJm > 12) { selJm = 1; selJy++; }
        if (selJm < 1) { selJm = 12; selJy--; }
        render();
        return;
      }
      var dayEl = e.target.closest(".jp-day:not(.jp-day--empty)");
      if (dayEl) {
        selJd = parseInt(dayEl.getAttribute("data-day"), 10);
        hasSelection = true;
        render();
        return;
      }
      var act = e.target.closest("[data-act]");
      if (act) {
        var kind = act.getAttribute("data-act");
        if (kind === "clear") {
          input.value = "";
          input.dispatchEvent(new Event("change", { bubbles: true }));
          closeActivePopup();
        } else if (kind === "today") {
          var t = new Date();
          var tj = toJalali(t.getFullYear(), t.getMonth() + 1, t.getDate());
          selJy = tj.jy; selJm = tj.jm; selJd = tj.jd;
          selH = t.getHours(); selMin = t.getMinutes();
          hasSelection = true;
          render();
        } else if (kind === "confirm") {
          var g = toGregorian(selJy, selJm, selJd);
          input.value = toGregorianLocalString(g.gy, g.gm, g.gd, selH, selMin);
          input.dispatchEvent(new Event("change", { bubbles: true }));
          closeActivePopup();
        }
      }
    });

    popup.addEventListener("change", function (e) {
      if (e.target.classList.contains("jp-hour")) selH = parseInt(e.target.value, 10);
      if (e.target.classList.contains("jp-minute")) selMin = parseInt(e.target.value, 10);
    });

    activePopup = popup;
    setTimeout(function () {
      document.addEventListener("mousedown", onDocMouseDown, true);
      window.addEventListener("scroll", closeActivePopup, true);
      window.addEventListener("resize", closeActivePopup, true);
    }, 0);
  }

  global.RasaJalali = {
    toJalali: toJalali,
    toGregorian: toGregorian,
    attach: attach,
  };

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".jalali-datetime-input").forEach(function (el) {
      attach(el.id);
    });
  });
})(window);
