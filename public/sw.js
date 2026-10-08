/*
 * Service worker de la Daara Nouroud Daareyni Kaolack.
 * Il ne garde aucune donnée personnelle : seulement la page « hors connexion » et le logo.
 */
const VERSION = 'ndk-v2';
const HORS_LIGNE = '/hors-ligne';
const FICHIERS = [HORS_LIGNE, '/images/logo-ndk.png'];

self.addEventListener('install', (evenement) => {
    evenement.waitUntil(caches.open(VERSION).then((cache) => cache.addAll(FICHIERS)));
    self.skipWaiting();
});

self.addEventListener('activate', (evenement) => {
    evenement.waitUntil(
        caches.keys().then((cles) => Promise.all(cles.filter((cle) => cle !== VERSION).map((cle) => caches.delete(cle)))),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (evenement) => {
    const adresse = new URL(evenement.request.url);

    // Le logo : d'abord la copie en mémoire, sinon le réseau.
    if (adresse.origin === self.location.origin && adresse.pathname === '/images/logo-ndk.png') {
        evenement.respondWith(
            caches.match(evenement.request).then((copie) => copie || fetch(evenement.request)),
        );

        return;
    }

    // Les pages : toujours le réseau ; sans réseau, la page « hors connexion ».
    if (evenement.request.mode === 'navigate') {
        evenement.respondWith(fetch(evenement.request).catch(() => caches.match(HORS_LIGNE)));
    }
});
