/*
 * Service worker de la Daara Nouroud Daareyni Kaolack.
 * - Fichiers communs (logo, styles) : gardés en mémoire.
 * - Carte de membre : une copie à jour, rangée à part, effacée à la déconnexion.
 * - Aucune autre page personnelle n'est gardée.
 */
const VERSION = 'ndk-v4';
const CACHE_CARTE = 'ndk-carte';
const HORS_LIGNE = '/hors-ligne';

self.addEventListener('install', (evenement) => {
    evenement.waitUntil(caches.open(VERSION).then((cache) => cache.addAll([HORS_LIGNE, '/images/logo-ndk.png'])));
    self.skipWaiting();
});

self.addEventListener('activate', (evenement) => {
    evenement.waitUntil(
        caches.keys().then((cles) => Promise.all(
            cles.filter((cle) => cle !== VERSION && cle !== CACHE_CARTE).map((cle) => caches.delete(cle)),
        )),
    );
    self.clients.claim();
});

function garder(nomCache, requete, reponse) {
    if (reponse.ok && !reponse.redirected) {
        const copie = reponse.clone();
        caches.open(nomCache).then((cache) => cache.put(requete, copie));
    }

    return reponse;
}

self.addEventListener('fetch', (evenement) => {
    const requete = evenement.request;
    const adresse = new URL(requete.url);

    if (requete.method !== 'GET' || adresse.origin !== self.location.origin) {
        return;
    }

    // Fichiers communs, identiques pour tous : d'abord la mémoire, sinon le réseau.
    if (adresse.pathname === '/images/logo-ndk.png' || adresse.pathname.startsWith('/build/assets/')) {
        evenement.respondWith(
            caches.match(requete).then((copie) => copie || fetch(requete).then((reponse) => garder(VERSION, requete, reponse))),
        );

        return;
    }

    // La carte et sa photo : d'abord le réseau (copie mise à jour), sinon la dernière copie.
    const estCarte = requete.mode === 'navigate' && adresse.pathname === '/ma-carte';
    const estPhotoDeLaCarte = adresse.pathname.startsWith('/photos/') && requete.referrer.endsWith('/ma-carte');

    if (estCarte || estPhotoDeLaCarte) {
        evenement.respondWith(
            fetch(requete)
                .then((reponse) => garder(CACHE_CARTE, requete, reponse))
                .catch(() => caches.match(requete, { cacheName: CACHE_CARTE })
                    .then((copie) => copie || (estCarte ? caches.match(HORS_LIGNE) : Response.error()))),
        );

        return;
    }

    // Les autres pages : toujours le réseau ; sans réseau, la page « hors connexion ».
    if (requete.mode === 'navigate') {
        evenement.respondWith(fetch(requete).catch(() => caches.match(HORS_LIGNE)));
    }
});

// Notifications sur le téléphone : affichage, puis ouverture de la bonne page au toucher.
self.addEventListener('push', (evenement) => {
    const donnees = evenement.data ? evenement.data.json() : {};

    evenement.waitUntil(self.registration.showNotification(donnees.title || 'Daara NDK', {
        body: donnees.body || '',
        icon: donnees.icon || '/images/icone-192.png',
        badge: donnees.badge || '/images/icone-192.png',
        data: donnees.data || {},
        lang: 'fr',
        vibrate: [100, 50, 100],
    }));
});

self.addEventListener('notificationclick', (evenement) => {
    evenement.notification.close();
    const adresse = (evenement.notification.data && evenement.notification.data.url) || '/mon-espace';

    evenement.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((fenetres) => {
        const ouverte = fenetres.find((fenetre) => new URL(fenetre.url).origin === self.location.origin);

        if (ouverte) {
            ouverte.navigate(adresse);

            return ouverte.focus();
        }

        return self.clients.openWindow(adresse);
    }));
});
