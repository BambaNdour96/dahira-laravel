<?php

namespace App\Support;

/**
 * Indicatifs téléphoniques proposés aux membres (Sénégal par défaut, puis la diaspora).
 */
final class Indicatifs
{
    public const PAR_DEFAUT = '221';

    /** @var array<int, string> */
    public const LISTE = [
        221 => '🇸🇳 Sénégal (+221)',
        220 => '🇬🇲 Gambie (+220)',
        222 => '🇲🇷 Mauritanie (+222)',
        223 => '🇲🇱 Mali (+223)',
        224 => '🇬🇳 Guinée (+224)',
        245 => '🇬🇼 Guinée-Bissau (+245)',
        225 => '🇨🇮 Côte d\'Ivoire (+225)',
        238 => '🇨🇻 Cap-Vert (+238)',
        237 => '🇨🇲 Cameroun (+237)',
        241 => '🇬🇦 Gabon (+241)',
        212 => '🇲🇦 Maroc (+212)',
        33 => '🇫🇷 France (+33)',
        39 => '🇮🇹 Italie (+39)',
        34 => '🇪🇸 Espagne (+34)',
        351 => '🇵🇹 Portugal (+351)',
        32 => '🇧🇪 Belgique (+32)',
        41 => '🇨🇭 Suisse (+41)',
        49 => '🇩🇪 Allemagne (+49)',
        44 => '🇬🇧 Royaume-Uni (+44)',
        1 => '🇺🇸 États-Unis, Canada (+1)',
    ];

    /** Ajoute l'indicatif choisi au numéro, sauf s'il en contient déjà un. */
    public static function combiner(?string $indicatif, ?string $numero): ?string
    {
        if ($numero === null) {
            return null;
        }

        $numero = trim($numero);

        if ($numero === '' || str_starts_with($numero, '+') || str_starts_with($numero, '00')) {
            return $numero;
        }

        if ($indicatif === null || $indicatif === self::PAR_DEFAUT || ! isset(self::LISTE[(int) $indicatif])) {
            return $numero;
        }

        return '+'.$indicatif.ltrim((string) preg_replace('/\D/', '', $numero), '0');
    }
}
