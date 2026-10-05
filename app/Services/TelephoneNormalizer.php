<?php

namespace App\Services;

/**
 * Règle 4.5 : un numéro sénégalais est stocké au format +221XXXXXXXXX,
 * un numéro étranger au format international. Renvoie null si invalide.
 */
final class TelephoneNormalizer
{
    public static function normaliser(?string $brut): ?string
    {
        if ($brut === null) {
            return null;
        }

        $tel = preg_replace('/[\s.\-]/', '', $brut) ?? '';

        if (str_starts_with($tel, '00')) {
            $tel = '+'.substr($tel, 2);
        }

        if (str_starts_with($tel, '+221')) {
            return preg_match('/^\+221[37]\d{8}$/', $tel) ? $tel : null;
        }

        if (preg_match('/^[37]\d{8}$/', $tel)) {
            return '+221'.$tel;
        }

        if (preg_match('/^\+\d{8,15}$/', $tel)) {
            return $tel;
        }

        return null;
    }
}
