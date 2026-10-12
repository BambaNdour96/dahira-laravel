// « Mes engagements » : verser petit à petit. Les boutons rapides et la case libre règlent le montant,
// qui met à jour le lien Wave et les boutons.
const fcfa = (montant) => `${new Intl.NumberFormat('fr-FR').format(montant).replace(/\u202f|\u00a0/g, ' ')} FCFA`;

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-versement]').forEach((formulaire) => {
        const champ = formulaire.querySelector('input[name="montant"]');
        const lienWave = formulaire.querySelector('[data-bouton-wave]');
        const boutons = formulaire.querySelectorAll('[data-montant]');
        const maximum = Number(formulaire.dataset.max || 0);

        if (!champ) {
            return;
        }

        const actualiser = () => {
            const montant = Math.max(0, Math.min(maximum, Math.round(Number(champ.value) || 0)));

            formulaire.querySelectorAll('[data-montant-affiche]').forEach((zone) => {
                zone.textContent = fcfa(montant);
            });

            if (lienWave && formulaire.dataset.lien) {
                const adresse = new URL(formulaire.dataset.lien);
                adresse.searchParams.set('amount', String(montant));
                lienWave.href = adresse.toString();
                lienWave.classList.toggle('pointer-events-none', montant < 100);
                lienWave.classList.toggle('opacity-50', montant < 100);
            }

            boutons.forEach((bouton) => {
                const choisi = Number(bouton.dataset.montant) === montant;
                bouton.classList.toggle('bg-emerald-800', choisi);
                bouton.classList.toggle('text-white', choisi);
                bouton.classList.toggle('bg-white', !choisi);
            });
        };

        boutons.forEach((bouton) => {
            bouton.addEventListener('click', () => {
                champ.value = bouton.dataset.montant;
                actualiser();
            });
        });

        champ.addEventListener('input', actualiser);
        actualiser();
    });
});
