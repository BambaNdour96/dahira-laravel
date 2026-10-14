<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Ce qui s'archive au lieu de s'effacer : masqué des listes de choix, toujours consultable.
 *
 * @property Carbon|null $archive_le
 * @property int|null $archive_par
 */
trait Archivable
{
    public function initializeArchivable(): void
    {
        $this->mergeCasts(['archive_le' => 'datetime']);
    }

    public function estArchive(): bool
    {
        return $this->archive_le !== null;
    }

    /**
     * Seulement ce qui n'est pas archivé.
     *
     * @param  Builder<static>  $query
     */
    public function scopeEnActivite(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('archive_le'));
    }
}
