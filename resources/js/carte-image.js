import { toPng } from 'html-to-image';

// « Télécharger en image » : la carte affichée devient une image PNG, à garder dans la galerie ou à partager.
document.addEventListener('DOMContentLoaded', () => {
    const bouton = document.querySelector('[data-telecharger-carte]');
    const carte = document.getElementById('carte-membre');
    const message = document.getElementById('message-carte-image');

    if (!bouton || !carte) {
        return;
    }

    bouton.addEventListener('click', async () => {
        bouton.disabled = true;
        try {
            const image = await toPng(carte, { pixelRatio: 3, cacheBust: true, backgroundColor: '#ffffff' });
            const lien = document.createElement('a');
            lien.href = image;
            lien.download = (carte.dataset.nomFichier || 'carte-membre') + '.png';
            document.body.appendChild(lien);
            lien.click();
            lien.remove();
            if (message) {
                message.textContent = 'Image enregistrée dans vos téléchargements.';
                message.className = 'mt-2 text-center text-sm font-medium text-emerald-800';
            }
        } catch (erreur) {
            if (message) {
                message.textContent = 'Impossible de créer l\'image. Utilisez plutôt le PDF.';
                message.className = 'mt-2 text-center text-sm text-red-700';
            }
        } finally {
            bouton.disabled = false;
        }
    });
});

// La carte reste affichée sans que l'écran ne s'éteigne, le temps que le contrôleur la scanne.
document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('carte-membre') || !('wakeLock' in navigator)) {
        return;
    }

    let verrou = null;
    const garderAllume = async () => {
        try {
            verrou = await navigator.wakeLock.request('screen');
        } catch (erreur) {
            verrou = null;
        }
    };

    garderAllume();
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && verrou === null) {
            garderAllume();
        }
    });
});
