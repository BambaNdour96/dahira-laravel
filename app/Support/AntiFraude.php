<?php

namespace App\Support;

use App\Models\Membre;
use App\Models\RegleValidation;
use App\Models\RepresentantFinance;
use App\Models\User;
use DomainException;

/**
 * Règles anti-fraude des paiements.
 */
final class AntiFraude
{
    /** Format d'une référence Wave, après mise en majuscules et suppression des espaces. */
    public const FORMAT_REFERENCE = '/^T_[A-Z0-9]{12,20}$/';

    /** Personne ne confirme son propre paiement : un autre membre du bureau doit le faire. */
    public static function verifierPasSoiMeme(Membre $membre, ?User $tresorier): void
    {
        if ($tresorier !== null && $membre->user_id === $tresorier->id) {
            throw new DomainException('Vous ne pouvez pas valider votre propre paiement : demandez à un autre membre du bureau.');
        }
    }

    /**
     * Règle complète : jamais soi-même ; le paiement d'un membre est confirmé par le Jewrine finance ;
     * pour un responsable, seulement par une personne autorisée par toutes les règles de ses postes.
     * Le super administrateur valide en secours (jamais son propre paiement).
     */
    public static function verifierValidateur(Membre $payeur, ?User $validateur): void
    {
        self::verifierPasSoiMeme($payeur, $validateur);

        if ($validateur === null || $validateur->can('paiements.secours')) {
            return;
        }

        $regles = RegleValidation::pour($payeur);

        if ($regles->isEmpty()) {
            if (! $validateur->can('paiements.valider') && ! RepresentantFinance::represente($validateur, $payeur)) {
                throw new DomainException('Seul le Jewrine finance ou son représentant dans la cellule confirme les paiements des membres (ou le super administrateur, en secours).');
            }

            return;
        }

        // Le payeur cumule peut-être plusieurs postes : le validateur doit être autorisé par toutes leurs règles.
        if (! RegleValidation::autorise($validateur, $payeur)) {
            throw new DomainException('Le paiement de ce responsable doit être validé par : '
                .$regles->map(fn (RegleValidation $regle): string => $regle->libelleValidateurs())->join(' ; et par ')
                .', ou par le super administrateur.');
        }
    }

    public static function normaliserReference(mixed $reference): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $reference) ?? '');
    }
}
