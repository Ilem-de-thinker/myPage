const CACHE_NAME = @js($version);
const OFFLINE_URL = @js($offlineUrl);
const STATIC_ASSETS = [OFFLINE_URL];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin || shouldSkip(url, request)) {
        return;
    }

    if (request.headers.get('accept')?.includes('text/html')) {
        event.respondWith(networkFirst(request));
        return;
    }

    if (['style', 'script', 'font', 'image'].includes(request.destination)) {
        event.respondWith(staleWhileRevalidate(request));
    }
});

function shouldSkip(url, request) {
    return url.pathname.startsWith('/dashboard')
        || url.pathname.startsWith('/admin')
        || url.pathname.startsWith('/api')
        || url.pathname.startsWith('/login')
        || url.pathname.startsWith('/register')
        || Boolean(request.headers.get('cookie'));
}

async function networkFirst(request) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(CACHE_NAME);
            await cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        return (await caches.match(request)) || caches.match(OFFLINE_URL);
    }
}

async function staleWhileRevalidate(request) {
    const cached = await caches.match(request);
    const network = fetch(request).then(async (response) => {
        if (response.ok) {
            const cache = await caches.open(CACHE_NAME);
            await cache.put(request, response.clone());
        }
        return response;
    }).catch(() => cached);

    return cached || network;
}
