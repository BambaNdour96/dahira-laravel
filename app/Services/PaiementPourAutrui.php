<?php

namespace App\Services;

use App\Enums\StatutMembre;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\User;
use App\Policies\MembrePolicy;
use DomainException;

/**
 * Payer pour un autre membre, distinct de « gérer la fiche d'un proche » : tout membre peut payer la cotisation ou le
 * Jàyante d'un autre membre qui a son propre compte (son épouse, par exemple). Le bénéficiaire est prévenu et reçoit
 * son reçu ; celui qui paie ne voit ni ses retards ni ses montants. Personne ne valide un paiement qu'il a déclaré.
 */
final class PaiementPourAutrui
{
    /** Le déclarant est-il un tiers : ni le membre lui-même, ni celui qui gère sa fiche ? */
    public static function estUnTiers(?User $declarant, Membre $membre): bool
    {
        return $declarant !== null
            && $declarant->id !== $membre->user_id
            && ! MembrePolicy::gereLaFiche($declarant, $membre);
    }

    /** Le paiement a-t-il été déclaré par un tiers ? */
    public static function parUnTiers(Paiement $paiement): bool
    {
        return $paiement->declarant !== null && self::estUnTiers($paiement->declarant, $paiement->membre);
    }

    /** Retrouve le bénéficiaire par son matricule ou son téléphone : un membre validé, qui a son propre compte. */
    public function trouver(string $saisie, User $payeur): Membre
    {
        $saisie = trim($saisie);
        $telephone = TelephoneNormalizer::normaliser($saisie);

        $membre = Membre::query()
            ->with('cellule')
            ->where(fn ($query) => $query->where('matricule', mb_strtoupper($saisie))
                ->when($telephone !== null, fn ($parTelephone) => $parTelephone->orWhere('telephone', $telephone)))
            ->first();

        if ($membre === null || $membre->statut !== StatutMembre::Valide) {
            throw new DomainException('Aucun membre validé ne correspond à ce matricule ou à ce téléphone.');
        }

        $this->verifier($membre, $payeur);

        return $membre;
    }

    /** Peut-il payer pour ce membre ? Jamais pour soi-même (on paie depuis ses propres pages). */
    public function verifier(Membre $membre, User $payeur): void
    {
        if ($membre->user_id === $payeur->id) {
            throw new DomainException('C\'est votre propre fiche : payez depuis « Mes cotisations » ou « Mes Sass et Jàyante ».');
        }

        if ($membre->statut !== StatutMembre::Valide) {
            throw new DomainException('Seuls les membres validés cotisent.');
        }

        if ($membre->user_id === null) {
            throw new DomainException(MembrePolicy::gereLaFiche($payeur, $membre)
                ? 'Vous gérez la fiche de ce proche : payez pour lui depuis « Ma famille ».'
                : 'Ce membre n\'a pas de compte : son paiement passe par le membre qui gère sa fiche.');
        }
    }
}
