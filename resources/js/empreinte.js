import { browserSupportsWebAuthn, startAuthentication, startRegistration } from '@simplewebauthn/browser';

const envoyer = async (url, jeton, donnees) => {
    const reponse = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': jeton },
        body: JSON.stringify(donnees),
    });
    const corps = await reponse.json().catch(() => ({}));
    if (!reponse.ok) {
        throw new Error(corps.message || 'Une erreur est survenue. Réessayez.');
    }
    return corps;
};

const options = async (url) => (await fetch(url, { headers: { Accept: 'application/json' } })).json();

const afficher = (bouton, texte, erreur = true) => {
    const zone = document.getElementById(bouton.dataset.message || '');
    if (zone) {
        zone.textContent = texte;
        zone.className = 'mt-2 text-sm ' + (erreur ? 'text-red-700' : 'font-medium text-emerald-800');
    }
};

const expliquer = (erreur, annule) => (erreur && erreur.name === 'NotAllowedError' ? annule : erreur?.message || 'Opération impossible.');

document.addEventListener('DOMContentLoaded', () => {
    const compatible = browserSupportsWebAuthn();
    const activee = (() => { try { return localStorage.getItem('ndk-empreinte') === '1'; } catch (e) { return false; } })();
    document.querySelectorAll('[data-empreinte]').forEach((bloc) => {
        const pourConnexion = bloc.querySelector('[data-connexion-empreinte]') !== null;
        bloc.classList.toggle('hidden', !compatible || (pourConnexion && !activee));
    });

    document.querySelectorAll('[data-activer-empreinte]').forEach((bouton) => {
        bouton.addEventListener('click', async () => {
            try {
                const cle = await startRegistration({ optionsJSON: await options(bouton.dataset.options) });
                const corps = await envoyer(bouton.dataset.enregistrer, bouton.dataset.jeton, { cle: JSON.stringify(cle) });
                afficher(bouton, corps.message, false);
                try { localStorage.setItem('ndk-empreinte', '1'); } catch (e) {}
                setTimeout(() => location.reload(), 1000);
            } catch (erreur) {
                afficher(bouton, expliquer(erreur, 'Activation annulée.'));
            }
        });
    });

    document.querySelectorAll('[data-connexion-empreinte]').forEach((bouton) => {
        bouton.addEventListener('click', async () => {
            try {
                const reponse = await startAuthentication({ optionsJSON: await options(bouton.dataset.options) });
                const corps = await envoyer(bouton.dataset.connecter, bouton.dataset.jeton, { reponse: JSON.stringify(reponse) });
                location.href = corps.redirection;
            } catch (erreur) {
                afficher(bouton, expliquer(erreur, 'Connexion annulée.'));
            }
        });
    });
});
