<?php

namespace App\Support;

use App\Models\Parametre;

/**
 * Liens de paiement Wave Business. On utilise en priorité les liens à montant fixe ;
 * à défaut, le lien général, avec le montant ajouté.
 */
final class LienWave
{
    public static function fraisCarte(int $montant): ?string
    {
        return Parametre::valeur('wave_lien_frais_carte')
            ?? self::avecMontant(Parametre::valeur('wave_lien_paiement'), $montant);
    }

    public static function cotisation(int $nombreMois, int $mensuel): ?string
    {
        return Parametre::valeur('wave_lien_'.$nombreMois.'_mois')
            ?? self::avecMontant(Parametre::valeur('wave_lien_paiement'), $nombreMois * $mensuel);
    }

    public static function avecMontant(?string $lien, int $montant): ?string
    {
        if ($lien === null || $lien === '') {
            return null;
        }

        $parties = parse_url($lien);

        if ($parties === false || ! isset($parties['host'])) {
            return $lien;
        }

        parse_str($parties['query'] ?? '', $parametres);
        $parametres['amount'] = (string) $montant;

        return ($parties['scheme'] ?? 'https').'://'.$parties['host']
            .($parties['path'] ?? '/')
            .'?'.http_build_query($parametres);
    }
}
