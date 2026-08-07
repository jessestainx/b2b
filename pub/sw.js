/* AWA — sw.js kill-switch (Onda 2D, 2026-08-08)
   Este service worker foi aposentado. Qualquer cliente antigo que buscar
   /sw.js recebe este arquivo, que limpa TODOS os caches desta origem e se
   auto-desregistra. NAO registrar novos service workers neste path. */
self.addEventListener('install', function () {
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys()
      .then(function (keys) { return Promise.all(keys.map(function (k) { return caches.delete(k); })); })
      .then(function () { return self.registration.unregister(); })
      .then(function () { return self.clients.matchAll({ type: 'window' }); })
      .then(function (clients) { clients.forEach(function (c) { c.navigate(c.url).catch(function () {}); }); })
      .catch(function () {})
  );
});
