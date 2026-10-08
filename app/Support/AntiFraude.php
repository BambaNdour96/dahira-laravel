<?php

namespace App\Support;

use App\Models\Membre;
use App\Models\RegleValidation;
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
     * Règle complète : jamais soi-même, et pour un poste clé du bureau,
     * seulement les postes désignés, ou le super administrateur en secours.
     */
    public static function verifierValidateur(Membre $payeur, ?User $validateur): void
    {
        self::verifierPasSoiMeme($payeur, $validateur);

        if ($validateur === null || $validateur->hasRole('super_administrateur') || $payeur->poste_id === null) {
            return;
        }

        $regle = RegleValidation::query()->where('poste_id', $payeur->poste_id)->first();

        if ($regle === null) {
            return;
        }

        $posteDuValidateur = $validateur->membre?->poste_id;

        if ($posteDuValidateur === null || ! in_array((int) $posteDuValidateur, $regle->idsValidateurs(), true)) {
            throw new DomainException('Le paiement de ce membre du bureau doit être validé par : '
                .$regle->libelleValidateurs().', ou par le super administrateur.');
        }
    }

    public static function normaliserReference(mixed $reference): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $reference) ?? '');
    }
}
