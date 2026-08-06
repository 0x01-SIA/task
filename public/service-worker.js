const CACHE_VERSION = 'task-app-static-v2';
const CACHE_PREFIX = 'task-app-';
const OFFLINE_URL = '/offline.html';
const OPTIONAL_STATIC_ASSET_PATHS = [
    '/assets/css/app.css',
    '/assets/js/app.js',
    '/assets/images/task-app-icon.png',
    '/assets/images/icons/icon-192.png',
    '/assets/images/icons/icon-512.png',
    '/assets/images/icons/icon-maskable-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(CACHE_VERSION);

        await cache.add(new Request(OFFLINE_URL, { cache: 'reload' }));

        await Promise.allSettled(
            OPTIONAL_STATIC_ASSET_PATHS.map(async (path) => {
                try {
                    await cache.add(new Request(path, { cache: 'reload' }));
                } catch (error) {
                    // Keep installation resilient when an optional static asset is unavailable.
                }
            })
        );

        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const cacheNames = await caches.keys();

        await Promise.all(
            cacheNames
                .filter((cacheName) => cacheName.startsWith(CACHE_PREFIX) && cacheName !== CACHE_VERSION)
                .map((cacheName) => caches.delete(cacheName))
        );

        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const requestUrl = new URL(request.url);

    if (requestUrl.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(handleNavigationRequest(request));
        return;
    }

    if (!isCacheableStaticAsset(requestUrl, request)) {
        return;
    }

    event.respondWith(handleStaticAssetRequest(request));
});

async function handleNavigationRequest(request) {
    try {
        return await fetch(request);
    } catch (error) {
        const cache = await caches.open(CACHE_VERSION);
        const offlineResponse = await cache.match(OFFLINE_URL);

        if (offlineResponse) {
            return offlineResponse;
        }

        return Response.error();
    }
}

async function handleStaticAssetRequest(request) {
    const cache = await caches.open(CACHE_VERSION);
    const cachedResponse = await cache.match(request);

    if (cachedResponse) {
        return cachedResponse;
    }

    try {
        const networkResponse = await fetch(request);

        if (isValidStaticResponse(networkResponse)) {
            await cache.put(request, networkResponse.clone());
        }

        return networkResponse;
    } catch (error) {
        if (cachedResponse) {
            return cachedResponse;
        }

        return Response.error();
    }
}

function isCacheableStaticAsset(requestUrl, request) {
    if (requestUrl.pathname === OFFLINE_URL || OPTIONAL_STATIC_ASSET_PATHS.includes(requestUrl.pathname)) {
        return true;
    }

    if (requestUrl.pathname.startsWith('/api/')) {
        return false;
    }

    if (request.destination === 'style' || request.destination === 'script' || request.destination === 'image') {
        return requestUrl.pathname.startsWith('/assets/');
    }

    return false;
}

function isValidStaticResponse(response) {
    return response.ok && (response.type === 'basic' || response.type === 'default');
}
