/*
 * Service worker de la Daara Nouroud Daareyni Kaolack.
 * Il ne garde aucune donnée personnelle : seulement la page « hors connexion » et le logo.
 */
const VERSION = 'ndk-v1';
const HORS_LIGNE = '/hors-ligne';

self.addEventListener('install', (evenement) => {
    evenement.waitUntil(
        caches.open(VERSION).then((cache) => cache.addAll([HORS_LIGNE, '/images/logo-ndk.png'])),
    );
    self.skipWaiting();
});

self.addEventListener('activate', (evenement) => {
    evenement.waitUntil(
        caches.keys().then((cles) => Promise.all(cles.filter((cle) => cle !== VERSION).map((cle) => caches.delete(cle)))),
    );
    self.clients.claim();
});

// Seules les ouvertures de pages sont concernées : sans réseau, on affiche la page « hors connexion ».
self.addEventListener('fetch', (evenement) => {
    if (evenement.request.mode !== 'navigate') {
        return;
    }

    evenement.respondWith(fetch(evenement.request).catch(() => caches.match(HORS_LIGNE)));
});
