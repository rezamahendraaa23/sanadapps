// Hanya menjaga halaman ujian bisa dibuka lagi saat putus koneksi. Halaman lain tidak disentuh.
const SHELL = 'sanad-shell-v1';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== location.origin) return;

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(caches.open(SHELL).then(async (cache) => {
            const hit = await cache.match(request);
            if (hit) return hit;
            const response = await fetch(request);
            if (response.ok) cache.put(request, response.clone());
            return response;
        }));

        return;
    }

    const match = url.pathname.match(/^\/ujian\/([0-9a-f-]{36})$/);

    if (match && request.mode === 'navigate') {
        const name = `sanad-ujian-${match[1]}`;

        event.respondWith(fetch(request).then(async (response) => {
            if (response.ok && ! response.redirected) (await caches.open(name)).put(request, response.clone());
            return response;
        }).catch(async () => {
            const hit = await (await caches.open(name)).match(request);
            return hit ?? Response.error();
        }));
    }
});
