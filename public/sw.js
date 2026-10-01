// Service worker minimal : condition technique pour que Chrome/Android propose
// l'installation de l'app (PWA) et pour pouvoir en générer un APK (TWA) ensuite.
// Ne met en cache QUE les fichiers statiques versionnés par Vite (/build/assets/,
// nom de fichier différent à chaque build) -- jamais les pages HTML ni les réponses
// Inertia/API, qui doivent toujours venir du réseau pour ne jamais afficher de stock,
// factures ou paiements périmés sur une application de gestion commerciale en direct.
const CACHE_NAME = 'controol-static-v1';

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
        )).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    if (event.request.method !== 'GET' || !url.pathname.startsWith('/build/assets/')) {
        return;
    }

    event.respondWith(
        caches.open(CACHE_NAME).then((cache) => cache.match(event.request).then((cached) => {
            if (cached) {
                return cached;
            }

            return fetch(event.request).then((response) => {
                cache.put(event.request, response.clone());

                return response;
            });
        }))
    );
});
