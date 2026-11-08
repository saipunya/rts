const CACHE_PREFIX = 'rts-member-' + encodeURIComponent(self.registration.scope) + '-';
const CACHE_NAME = CACHE_PREFIX + 'offline-v3';
const STATIC_ASSETS = [
  'assets/offline/bootstrap-5.3.3.min.css',
  'assets/offline/bootstrap-icons-1.11.3.min.css',
  'assets/offline/bootstrap-5.3.2.bundle.min.js',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS)).catch(() => {})
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k.startsWith(CACHE_PREFIX) && k !== CACHE_NAME).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  if (event.request.method !== 'GET') return;

  if (STATIC_ASSETS.some((asset) => new URL(asset, self.registration.scope).href === event.request.url)) {
    event.respondWith(
      caches.open(CACHE_NAME).then((cache) => cache.match(event.request)).then((cached) => cached || fetch(event.request).then((res) => {
        const clone = res.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
        return res;
      }))
    );
    return;
  }

  if (url.pathname.endsWith('.php')) {
    event.respondWith(fetch(event.request));
  }
});
