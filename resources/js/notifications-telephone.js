// « Notifications sur ce téléphone » : abonnement Web Push, géré depuis Paramètres.
const cleEnOctets = (cle) => {
    const remplissage = '='.repeat((4 - (cle.length % 4)) % 4);
    const brut = atob((cle + remplissage).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from([...brut].map((caractere) => caractere.charCodeAt(0)));
};

const envoyer = (url, methode, jeton, corps) => fetch(url, {
    method: methode,
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': jeton },
    body: JSON.stringify(corps),
});

document.addEventListener('DOMContentLoaded', async () => {
    const bloc = document.querySelector('[data-notifications-telephone]');
    if (!bloc) {
        return;
    }

    const etat = bloc.querySelector('[data-etat]');
    const activer = bloc.querySelector('[data-activer]');
    const desactiver = bloc.querySelector('[data-desactiver]');
    const afficher = (texte, actif) => {
        etat.textContent = texte;
        activer.classList.toggle('hidden', actif !== false);
        desactiver.classList.toggle('hidden', actif !== true);
    };

    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !bloc.dataset.cle) {
        afficher('Ce téléphone ou ce navigateur ne permet pas les notifications. Sur iPhone, installez d\'abord l\'application sur l\'écran d\'accueil.', null);
        return;
    }

    const enregistrement = await navigator.serviceWorker.ready;
    const abonnement = await enregistrement.pushManager.getSubscription();
    afficher(abonnement ? '✓ Notifications activées sur ce téléphone.' : 'Notifications désactivées sur ce téléphone.', Boolean(abonnement));

    activer.addEventListener('click', async () => {
        try {
            if ((await Notification.requestPermission()) !== 'granted') {
                afficher('Autorisation refusée. Vous pouvez la donner dans les réglages du navigateur, rubrique « Notifications ».', false);
                return;
            }
            const nouvel = await enregistrement.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: cleEnOctets(bloc.dataset.cle),
            });
            const reponse = await envoyer(bloc.dataset.enregistrer, 'POST', bloc.dataset.jeton, nouvel.toJSON());
            afficher(reponse.ok ? '✓ Notifications activées sur ce téléphone.' : 'Activation impossible. Réessayez.', reponse.ok);
        } catch (erreur) {
            afficher('Activation impossible sur ce téléphone.', false);
        }
    });

    desactiver.addEventListener('click', async () => {
        const actuel = await enregistrement.pushManager.getSubscription();
        if (actuel) {
            await envoyer(bloc.dataset.retirer, 'DELETE', bloc.dataset.jeton, { endpoint: actuel.endpoint });
            await actuel.unsubscribe();
        }
        afficher('Notifications désactivées sur ce téléphone.', false);
    });
});
