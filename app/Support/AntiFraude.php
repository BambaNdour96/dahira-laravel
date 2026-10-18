<?php

namespace App\Support;

use App\Models\Membre;
use App\Models\Paiement;
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

    /**
     * Personne ne confirme son propre paiement : un autre membre du bureau doit le faire.
     * Le paiement d'un proche dont on gère la fiche compte comme le sien.
     */
    public static function verifierPasSoiMeme(Membre $membre, ?User $tresorier): void
    {
        if ($tresorier !== null && $membre->user_id === $tresorier->id) {
            throw new DomainException('Vous ne pouvez pas valider votre propre paiement : demandez à un autre membre du bureau.');
        }

        if ($tresorier !== null && $membre->responsable !== null && $membre->responsable->user_id === $tresorier->id) {
            throw new DomainException('Vous gérez la fiche de ce proche : son paiement est validé par un autre membre du bureau.');
        }
    }

    /**
     * La règle complète, pour une déclaration : en plus, personne ne valide un paiement qu'il a lui-même déclaré pour
     * un autre membre (un proche, son épouse…).
     */
    public static function verifierPaiement(Paiement $paiement, ?User $validateur): void
    {
        self::verifierPasSoiMeme($paiement->membre, $validateur);

        if ($validateur !== null && $paiement->declare_par === $validateur->id) {
            throw new DomainException('Vous avez déclaré ce paiement pour un autre membre : il est validé par un autre membre du bureau.');
        }

        self::verifierValidateur($paiement->membre, $validateur);
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
