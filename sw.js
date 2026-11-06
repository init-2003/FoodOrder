/* Only the offline notice is cached; bills, sessions, and orders stay network-only. */
const cachePrefix = `imdee-offline:${encodeURIComponent(new URL(self.registration.scope).pathname)}:`;
const cacheName = `${cachePrefix}v2`;
const offlineUrl = new URL('offline.html', self.registration.scope).href;

self.addEventListener('install', event => {
  event.waitUntil(caches.open(cacheName).then(cache => cache.add(new Request(offlineUrl, { cache: 'reload' }))).then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
  event.waitUntil(caches.keys().then(keys => Promise.all(
    keys.filter(key => key.startsWith(cachePrefix) && key !== cacheName).map(key => caches.delete(key))
  )).then(() => self.clients.claim()));
});

self.addEventListener('fetch', event => {
  const request = event.request;
  if (request.method !== 'GET' || request.mode !== 'navigate' || new URL(request.url).origin !== self.location.origin) return;
  event.respondWith(fetch(request).catch(async () => {
    const cached = await caches.match(offlineUrl);
    return cached || new Response('ไม่สามารถเชื่อมต่อได้ กรุณาลองใหม่อีกครั้ง', {
      status: 503,
      headers: { 'Content-Type': 'text/plain; charset=utf-8', 'Cache-Control': 'no-store' },
    });
  }));
});
