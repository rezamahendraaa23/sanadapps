// Layar TV tetap bisa dibuka lagi saat internet putus: halaman, aset, dan media disimpan.
var CACHE = 'sanad-display-v1';
var SHELL = ['/display', '/tv/display.css?v=1', '/tv/display.js?v=1', '/tv/fonts/fredoka.woff2', '/tv/fonts/nunito.woff2', '/tv/fonts/amiri.woff2'];

self.addEventListener('install', function (event) {
    self.skipWaiting();
    event.waitUntil(caches.open(CACHE).then(function (cache) { return cache.addAll(SHELL); }));
});

self.addEventListener('activate', function (event) { event.waitUntil(self.clients.claim()); });

self.addEventListener('message', function (event) {
    if (event.data && event.data.type === 'cache-media') {
        caches.open(CACHE).then(function (cache) {
            event.data.urls.forEach(function (url) {
                cache.match(url).then(function (hit) { if (!hit) { cache.add(url).catch(function () {}); } });
            });
        });
    }
});

self.addEventListener('fetch', function (event) {
    var url = new URL(event.request.url);

    if (event.request.method !== 'GET' || url.origin !== location.origin) { return; }

    var isShell = url.pathname === '/display' || url.pathname.indexOf('/tv/') === 0;
    var isMedia = url.pathname.indexOf('/api/v1/display/media/') === 0;

    if (!isShell && !isMedia) { return; }

    event.respondWith(fetch(event.request).then(function (response) {
        if (response.ok) {
            var copy = response.clone();
            caches.open(CACHE).then(function (cache) { cache.put(event.request, copy); });
        }
        return response;
    }).catch(function () {
        return caches.match(event.request).then(function (hit) { return hit || Response.error(); });
    }));
});
