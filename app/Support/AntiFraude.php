<?php

namespace App\Support;

use App\Models\Membre;
use App\Models\User;
use DomainException;

/**
 * Règles anti-fraude des paiements.
 */
final class AntiFraude
{
    /** Format accepté pour une référence Wave, après mise en majuscules et suppression des espaces. */
    public const FORMAT_REFERENCE = '/^T_[A-Z0-9]{12,20}$/';

    /** Personne ne confirme son propre paiement : un autre membre du bureau doit le faire. */
    public static function verifierPasSoiMeme(Membre $membre, ?User $tresorier): void
    {
        if ($tresorier !== null && $membre->user_id === $tresorier->id) {
            throw new DomainException('Vous ne pouvez pas valider votre propre paiement : demandez à un autre membre du bureau.');
        }
    }

    public static function normaliserReference(mixed $reference): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $reference) ?? '');
    }
}
