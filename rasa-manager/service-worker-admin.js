/**
 * rasa-manager/service-worker-admin.js
 * سرویس‌ورکر مخصوص PWA «پنل ادمین» — جدا از سرویس‌ورکر بخش وبلاگ‌ها.
 *
 * پنل ادمین همیشه داده‌ی زنده و تازه لازم دارد، پس این سرویس‌ورکر هیچ
 * کشی برای صفحات یا API انجام نمی‌دهد؛ تنها وجودش برای «نصب‌پذیر» شدن
 * پنل به‌عنوان یک اپلیکیشن مجزا (طبق الزامات PWA) لازم است.
 */

const ADMIN_CACHE_VERSION = 'resa-admin-v1';

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== ADMIN_CACHE_VERSION).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

// عمداً هیچ fetch handler ای اضافه نمی‌شود؛ همه‌ی درخواست‌های پنل ادمین
// همیشه مستقیم از شبکه انجام می‌شوند تا داده‌ها هرگز کهنه نمایش داده نشوند.
