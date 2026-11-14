const CACHE_NAME = 'etunggakan-static-v3';
const STATIC_ASSETS = [
    'manifest.json',
    'assets/css/style.css',
    'assets/js/charts.js',
    'assets/js/app.js',
    'assets/icons/icon.svg'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    if (event.request.method !== 'GET' || url.pathname.endsWith('api.php') || url.pathname.endsWith('.php')) {
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then((res) => {
                if (res && res.ok) {
                    const clone = res.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                }
                return res;
            })
            .catch(() => caches.match(event.request))
    );
});

