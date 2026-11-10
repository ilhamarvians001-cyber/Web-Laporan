/* ============================================================
   Authentic Gold PWA (Native-like Android) — Service Worker
   ============================================================
   - Naikkan VERSION setiap deploy perubahan agar cache lama dihapus
   - Cache TIDAK menyimpan: nota ber-token, AJAX, backup, login
   ============================================================ */

const VERSION = 'v1.2.0';

const CACHE_STATIC = `laser-static-${VERSION}`;
const CACHE_PAGES  = `laser-pages-${VERSION}`;
const CACHE_CDN    = `laser-cdn-${VERSION}`;

const PRECACHE_ASSETS = [
    'offline.html',
    'icons/icon-192.png',
    'icons/icon-512.png',
    'icons/icon-maskable-192.png',
    'icons/icon-maskable-512.png'
];

/* Path yang TIDAK PERNAH di-cache */
const NEVER_CACHE_EXT = [
    '/pelanggan_ajax.php',  // API AJAX
    '/backup.php',          // file download dinamis
    '/installer.php',       // instalasi
];

/* CDN yang boleh cache-first */
const CDN_HOSTS = [
    'cdn.jsdelivr.net',
    'cdnjs.cloudflare.com',
    'unpkg.com',
    'fonts.googleapis.com',
    'fonts.gstatic.com',
];

/* Ekstensi static aset */
const STATIC_EXT = /\.(png|jpg|jpeg|webp|svg|gif|ico|woff2?|ttf|eot|css)$/i;

/* ============ INSTALL ============ */
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_STATIC)
            .then(cache => cache.addAll(PRECACHE_ASSETS))
            .catch(err => console.warn('[SW] Precache partial failure:', err))
            .then(() => self.skipWaiting())
    );
});

/* ============ ACTIVATE ============ */
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(
                keys
                    .filter(k => !k.endsWith(VERSION))
                    .map(k => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

/* ============ FETCH ============ */
self.addEventListener('fetch', (event) => {
    const req = event.request;
    const url = new URL(req.url);

    // Hanya GET, hanya http(s)
    if (req.method !== 'GET') return;
    if (!url.protocol.startsWith('http')) return;

    // Skip API/AJAX/download — biarkan browser handle native
    if (NEVER_CACHE_EXT.some(p => url.pathname.endsWith(p))) return;

    // Skip nota ber-token — selalu network (privacy & token dinamis)
    if (url.searchParams.has('token')) {
        event.respondWith(networkOnly(req));
        return;
    }

    // Skip logout — jangan pernah cache
    if (url.pathname.endsWith('/logout.php')) {
        event.respondWith(networkOnly(req));
        return;
    }

    // CDN → cache-first
    if (isCdn(url)) {
        event.respondWith(cacheFirst(req, CACHE_CDN));
        return;
    }

    // Uploads (logo, foto) → network-first (biar update langsung terlihat)
    if (url.pathname.includes('/uploads/')) {
        event.respondWith(networkFirst(req, CACHE_STATIC));
        return;
    }

    // Static asset (CSS, gambar, font) → cache-first
    if (STATIC_EXT.test(url.pathname)) {
        event.respondWith(cacheFirst(req, CACHE_STATIC));
        return;
    }

    // Dokumen PHP / navigasi → network-first, fallback cache, fallback offline
    if (req.destination === 'document'
        || url.pathname.endsWith('.php')
        || url.pathname.endsWith('/')) {
        event.respondWith(networkFirstPage(req));
        return;
    }

    // Default: network-first
    event.respondWith(networkFirst(req, CACHE_STATIC));
});

/* ============ STRATEGIES ============ */

async function cacheFirst(req, cacheName) {
    const cached = await caches.match(req);
    if (cached) return cached;
    try {
        const res = await fetch(req);
        if (res && res.ok) {
            const cache = await caches.open(cacheName);
            cache.put(req, res.clone());
        }
        return res;
    } catch (_) {
        return new Response('', { status: 503, statusText: 'Offline' });
    }
}

async function networkFirst(req, cacheName) {
    try {
        const res = await fetch(req);
        if (res && res.ok) {
            const cache = await caches.open(cacheName);
            cache.put(req, res.clone());
        }
        return res;
    } catch (_) {
        const cached = await caches.match(req);
        return cached || new Response('', { status: 503, statusText: 'Offline' });
    }
}

async function networkFirstPage(req) {
    try {
        const res = await fetch(req);
        if (res && res.ok && res.status === 200) {
            const cache = await caches.open(CACHE_PAGES);
            cache.put(req, res.clone());
        }
        return res;
    } catch (_) {
        const cached = await caches.match(req);
        if (cached) return cached;
        const offline = await caches.match('offline.html');
        return offline || new Response(
            '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:40px"><h1>📡 Offline</h1><p>Anda sedang offline.</p></body></html>',
            { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        );
    }
}

async function networkOnly(req) {
    try {
        return await fetch(req);
    } catch (_) {
        return new Response('', { status: 503, statusText: 'Offline' });
    }
}

/* ============ HELPERS ============ */
function isCdn(url) {
    return CDN_HOSTS.some(h => url.hostname === h || url.hostname.endsWith('.' + h));
}