/* Service worker Hadirku.
   Hanya untuk syarat instal aplikasi + halaman cadangan saat offline.
   Halaman & API TIDAK disimpan (login, absen, token CSRF harus selalu terbaru dari server). */
const CACHE = 'hadirku-offline-v1';
const OFFLINE = new URL('offline.html', self.registration.scope).href;

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.add(OFFLINE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', e => {
  const r = e.request;
  if (r.mode !== 'navigate') return; // selain pindah halaman: biarkan browser menangani
  e.respondWith(fetch(r).catch(() => caches.match(OFFLINE)));
});
