<?php

namespace App\Services;

use App\Models\Membre;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Règle 4.2 : matricule NDD-AAAA-NNNN, attribué une seule fois.
 * AAAA est l'année de validation, ou l'année d'adhésion pour un import.
 */
final class MatriculeService
{
    private const ESSAIS = 5;

    public function attribuer(Membre $membre, ?int $annee = null): string
    {
        if ($membre->matricule !== null) {
            return $membre->matricule;
        }

        $prefixe = 'NDD-'.($annee ?? now()->year).'-';

        for ($essai = 1; ; $essai++) {
            try {
                return DB::transaction(function () use ($membre, $prefixe): string {
                    $dernier = Membre::query()
                        ->where('matricule', 'like', $prefixe.'%')
                        ->orderByDesc('matricule')
                        ->lockForUpdate()
                        ->value('matricule');

                    $numero = $dernier ? ((int) substr((string) $dernier, -4)) + 1 : 1;

                    $membre->matricule = $prefixe.str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
                    $membre->save();

                    return $membre->matricule;
                });
            } catch (UniqueConstraintViolationException $e) {
                // Deux validations simultanées : on recommence avec le numéro suivant.
                $membre->matricule = null;

                if ($essai >= self::ESSAIS) {
                    throw $e;
                }
            }
        }
    }
}
