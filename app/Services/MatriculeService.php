<?php

namespace App\Services;

use App\Models\Membre;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Règle 4.2 : matricule NDD-AAAA-NNNN, attribué une seule fois, à la validation.
 */
final class MatriculeService
{
    private const ESSAIS = 5;

    public function attribuer(Membre $membre): string
    {
        if ($membre->matricule !== null) {
            return $membre->matricule;
        }

        for ($essai = 1; ; $essai++) {
            try {
                return DB::transaction(function () use ($membre): string {
                    $prefixe = 'NDD-'.now()->year.'-';

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
