<?php

namespace App\Services;

use App\Models\Paiement;

/**
 * Numéros de reçu par nature : INS-AAAA-NNNN (inscription), COT-AAAA-NNNN (cotisation).
 * Chaque nature a sa propre suite, qui repart à 0001 chaque année.
 */
final class RecuService
{
    public function prochainNumero(int $annee, string $prefixe = 'REC'): string
    {
        $debut = $prefixe.'-'.$annee.'-';

        $dernier = Paiement::query()
            ->where('numero_recu', 'like', $debut.'%')
            ->orderByDesc('numero_recu')
            ->lockForUpdate()
            ->value('numero_recu');

        $suivant = is_string($dernier) ? ((int) substr($dernier, strlen($debut))) + 1 : 1;

        return $debut.str_pad((string) $suivant, 4, '0', STR_PAD_LEFT);
    }
}
