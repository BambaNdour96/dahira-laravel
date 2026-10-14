<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ce qui s'annule au lieu de s'effacer (« rien ne se perd ») : la date, l'auteur et le motif restent.
 *
 * @property Carbon|null $annule_le
 * @property int|null $annule_par
 * @property string|null $motif_annulation
 */
trait Annulable
{
    public function initializeAnnulable(): void
    {
        $this->mergeCasts(['annule_le' => 'datetime']);
    }

    public function estAnnule(): bool
    {
        return $this->annule_le !== null;
    }

    /**
     * Seulement ce qui n'est pas annulé.
     *
     * @param  Builder<static>  $query
     */
    public function scopeMaintenus(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('annule_le'));
    }

    /** @return BelongsTo<User, $this> */
    public function annulePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annule_par');
    }
}
