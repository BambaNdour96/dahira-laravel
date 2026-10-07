<?php

namespace App\Services;

use App\Models\Paiement;

/**
 * Numéros de reçu : REC-AAAA-NNNN, qui repartent à 0001 chaque année.
 */
final class RecuService
{
    public function prochainNumero(int $annee): string
    {
        $prefixe = 'REC-'.$annee.'-';

        $dernier = Paiement::query()
            ->where('numero_recu', 'like', $prefixe.'%')
            ->orderByDesc('numero_recu')
            ->lockForUpdate()
            ->value('numero_recu');

        $suivant = is_string($dernier) ? ((int) substr($dernier, strlen($prefixe))) + 1 : 1;

        return $prefixe.str_pad((string) $suivant, 4, '0', STR_PAD_LEFT);
    }
}
