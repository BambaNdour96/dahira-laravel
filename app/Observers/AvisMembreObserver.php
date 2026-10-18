<?php

namespace App\Observers;

use App\Enums\StatutCarte;
use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Models\Carte;
use App\Models\Membre;
use App\Models\Paiement;
use App\Notifications\NotificationMembre;
use Illuminate\Database\Eloquent\Model;

/**
 * Prévient le membre de ce qui le concerne : statut, paiements, carte.
 */
class AvisMembreObserver
{
    public function created(Model $modele): void
    {
        if ($modele instanceof Paiement && $modele->statut === StatutPaiement::Confirme) {
            self::paiementConfirme($modele);
        }
    }

    public function updated(Model $modele): void
    {
        // Bienvenue : envoyée quand le matricule est attribué, pour pouvoir l'indiquer.
        if ($modele instanceof Membre && $modele->wasChanged('matricule') && $modele->getOriginal('matricule') === null && $modele->matricule !== null) {
            self::avertir($modele, 'Bienvenue dans la Daara !', 'Votre adhésion est validée. Votre matricule est '.$modele->matricule.', et votre carte de membre est disponible.', route('carte'), 'Voir ma carte');
        }

        if ($modele instanceof Membre && $modele->wasChanged('statut')) {
            self::changementDeStatut($modele);
        }

        if ($modele instanceof Paiement && $modele->wasChanged('statut')) {
            if ($modele->statut === StatutPaiement::Confirme) {
                self::paiementConfirme($modele);
            }

            if ($modele->statut === StatutPaiement::Refuse) {
                self::avertir(
                    $modele->membre,
                    'Paiement refusé',
                    'Votre déclaration de '.self::fcfa($modele->montant).' a été refusée. Motif : '.($modele->motif_refus ?? 'non précisé').'.',
                    route('espace'),
                    'Ouvrir mon compte',
                );
            }
        }

        if ($modele instanceof Carte && $modele->wasChanged('statut') && $modele->statut === StatutCarte::Revoquee) {
            self::avertir(
                $modele->membre,
                'Votre carte a été remplacée',
                'L\'ancienne carte n\'est plus valide. Votre nouvelle carte de membre est disponible dans votre compte.',
                route('carte'),
                'Voir ma nouvelle carte',
            );
        }
    }

    private static function changementDeStatut(Membre $membre): void
    {
        $avant = $membre->getOriginal('statut');

        $avis = match ($membre->statut) {
            StatutMembre::Accepte => ['Votre demande est acceptée',
                'Réglez maintenant les frais de carte (1 000 FCFA) depuis votre compte, dans un délai de 10 jours.', route('espace'), 'Ouvrir mon compte'],
            StatutMembre::ACorriger => ['Votre fiche est à corriger',
                'Le bureau vous demande de compléter ou corriger votre fiche. Consultez votre compte pour voir ce qu\'il faut modifier.', route('espace'), 'Ouvrir mon compte'],
            StatutMembre::Rejete => ['Votre demande n\'a pas été retenue',
                'Consultez votre compte pour en connaître le motif.', route('espace'), 'Ouvrir mon compte'],
            StatutMembre::Expire => ['Votre demande a expiré',
                'Le délai de 10 jours pour régler les frais de carte est dépassé. Vous pouvez renvoyer votre demande depuis votre compte.', route('espace'), 'Ouvrir mon compte'],
            StatutMembre::Suspendu => ['Votre adhésion est suspendue',
                'Votre carte n\'est plus valide pour le moment. Contactez votre Jewrine de cellule pour plus d\'informations.', route('espace'), 'Ouvrir mon compte'],
            StatutMembre::Valide => $avant === StatutMembre::Suspendu
                ? ['Votre adhésion est réactivée', 'Votre carte de membre est de nouveau valide.', route('carte'), 'Voir ma carte']
                : null,
            default => null,
        };

        if ($avis !== null) {
            self::avertir($membre, ...$avis);
        }
    }

    private static function paiementConfirme(Paiement $paiement): void
    {
        self::avertir(
            $paiement->membre,
            'Paiement confirmé',
            $paiement->libelleObjet().' : '.self::fcfa($paiement->montant).'. Votre reçu '.$paiement->numero_recu.' est disponible.',
            route('recus.show', $paiement),
            'Télécharger mon reçu',
        );
    }

    /**
     * Le membre est prévenu sur son compte. Un proche sans compte (enfant, adulte sans téléphone) l'est par le membre
     * qui gère sa fiche, avec un lien vers sa fiche dans « Ma famille ».
     */
    private static function avertir(Membre $membre, string $titre, string $message, ?string $lien, ?string $libelle): void
    {
        if ($membre->user !== null) {
            $membre->user->notify(new NotificationMembre($titre, $message, $lien, $libelle));

            return;
        }

        $membre->responsable?->user?->notify(new NotificationMembre(
            $membre->prenom.' : '.$titre,
            'Pour '.$membre->prenom.' '.$membre->nom.'. '.$message,
            route('famille.show', $membre),
            'Voir sa fiche',
        ));
    }

    private static function fcfa(int $montant): string
    {
        return number_format($montant, 0, ',', ' ').' FCFA';
    }
}
