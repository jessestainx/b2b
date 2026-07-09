/**
 * AWA Motos - Service Worker
 *
 * Sprint 1 (PWA + Performance):
 * - Cache estatico (CSS, JS, imagens) - 30 dias
 * - Network-first para paginas HTML
 * - Offline fallback
 *
 * Versao: 1.1.0
 */

const CACHE_VERSION = 'awa-v7';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const RUNTIME_CACHE = `${CACHE_VERSION}-runtime`;

const STATIC_ASSETS = [
    '/',
    '/static/version*/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/styles-m.min.css',
    '/static/version*/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/styles-l.min.css',
    '/static/version*/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/print.min.css',
    '/media/logo/stores/1/logo_161x92_1.png',
    '/offline',
];

// Install - cache static assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
    );
});

// Activate - cleanup old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((k) => !k.startsWith(CACHE_VERSION))
                    .map((k) => caches.delete(k))
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch - network-first for HTML, cache-first for assets
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    const acceptHeader = request.headers.get('accept') || '';
    const isMenuControllerAsset = /\/static\/version[^/]+\/frontend\/AWA_Custom\/ayo_home5_child\/[^/]+\/js\/(awa-menu-controller|awa-header-minicart-ui-v2)\.js$/.test(url.pathname)
        || /\/js\/(awa-menu-controller|awa-header-minicart-ui-v2)\.js$/.test(url.pathname);

    // Skip non-GET
    if (request.method !== 'GET') return;

    // Skip cross-origin
    if (url.origin !== location.origin) return;

    // Skip admin/cart/checkout (sensitive)
    if (url.pathname.match(/^\/(admin|customer|checkout|cart|wishlist|sales)/)) return;

    // HTML pages - network-first
    if (request.mode === 'navigate' || acceptHeader.includes('text/html')) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    // Cache successful responses
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(RUNTIME_CACHE).then((c) => c.put(request, clone));
                    }
                    return response;
                })
                .catch(() => caches.match(request).then((r) => r || caches.match('/')))
        );
        return;
    }

    // JS do controller do menu vertical e do header/minicart: sempre prioriza
    // rede para evitar lock em bundle antigo no cache do service worker
    // (2026-07-08: mesmo bug do menu-controller, reproduzido no minicart).
    if (isMenuControllerAsset) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok && response.type === 'basic') {
                        const clone = response.clone();
                        caches.open(RUNTIME_CACHE).then((c) => c.put(request, clone));
                    }
                    return response;
                })
                .catch(() => {
                    return caches.match(request).then((cached) => {
                        if (cached) {
                            return cached;
                        }
                        return fetch(request);
                    });
                })
        );
        return;
    }

    // Static assets - cache-first
    event.respondWith(
        caches.match(request).then((cached) => {
            if (cached) return cached;
            return fetch(request).then((response) => {
                if (response.ok && response.type === 'basic') {
                    const clone = response.clone();
                    caches.open(RUNTIME_CACHE).then((c) => c.put(request, clone));
                }
                return response;
            });
        })
    );
});
