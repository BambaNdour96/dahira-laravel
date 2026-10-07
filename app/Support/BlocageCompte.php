<?php

namespace App\Support;

use App\Services\TelephoneNormalizer;

/**
 * Après 5 mots de passe faux en 15 minutes, le numéro est bloqué 15 minutes.
 */
final class BlocageCompte
{
    public const ESSAIS = 5;

    public const DUREE_SECONDES = 900;

    /** @param  array<string, mixed>  $identifiants */
    public static function cle(array $identifiants): ?string
    {
        $telephone = $identifiants['telephone'] ?? null;

        if (! is_string($telephone) || $telephone === '') {
            return null;
        }

        return 'blocage-compte:'.(TelephoneNormalizer::normaliser($telephone) ?? $telephone);
    }
}
