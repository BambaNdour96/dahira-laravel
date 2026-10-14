<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Une désignation qui prend fin sans être effacée. Par défaut, seules les désignations
 * en cours sont lues ; l'historique s'obtient avec withoutGlobalScope(self::EN_COURS).
 *
 * @property Carbon|null $fin_le
 * @property int|null $fin_par
 */
trait PrendFin
{
    public const EN_COURS = 'en_cours';

    public static function bootPrendFin(): void
    {
        static::addGlobalScope(self::EN_COURS, function (Builder $query): void {
            $query->whereNull($query->qualifyColumn('fin_le'));
        });
    }

    public function initializePrendFin(): void
    {
        $this->mergeCasts(['fin_le' => 'datetime']);
    }

    public function estTerminee(): bool
    {
        return $this->fin_le !== null;
    }
}
