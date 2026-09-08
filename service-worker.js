/**
 * service-worker.js
 * سرویس‌ورکر رسا تیم — عمدتاً برای PWA بخش «وبلاگ‌ها»:
 *  - نصب‌پذیری اپ (شرط لازم برای رویداد beforeinstallprompt)
 *  - کش کردن پوسته‌ی اپ (App Shell) برای بارگذاری سریع‌تر
 *  - دسترسی حداقلی آفلاین وقتی اینترنت قطع است
 */

const CACHE_VERSION = 'resa-v1';
const APP_SHELL = [
  '/articles.html',
  '/css/style.css',
  '/css/articles.css',
  '/js/main.js',
  '/js/articles.js',
  '/assets/logo/web-app-manifest-192x192.png',
  '/assets/logo/web-app-manifest-512x512.png',
];

self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) => cache.addAll(APP_SHELL).catch(() => {}))
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_VERSION).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // درخواست‌های API همیشه از شبکه (داده‌ها باید تازه باشند)
  if (url.pathname.startsWith('/api/')) return;

  // ناوبری بین صفحات: اول شبکه، در نبود اینترنت از کش/صفحه وبلاگ‌ها کش‌شده
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req).catch(() =>
        caches.match(req).then((cached) => cached || caches.match('/articles.html'))
      )
    );
    return;
  }

  // فایل‌های استاتیک (css/js/تصاویر/فونت): اول کش، در پس‌زمینه به‌روزرسانی شود
  event.respondWith(
    caches.match(req).then((cached) => {
      const networkFetch = fetch(req)
        .then((res) => {
          if (res && res.status === 200) {
            const clone = res.clone();
            caches.open(CACHE_VERSION).then((cache) => cache.put(req, clone));
          }
          return res;
        })
        .catch(() => cached);
      return cached || networkFetch;
    })
  );
});
