<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Les cellules successives d'un membre : du … au … (la cellule en cours n'a pas de fin).
 *
 * @property int $id
 * @property int $membre_id
 * @property int $cellule_id
 * @property Carbon $debut_le
 * @property Carbon|null $fin_le
 * @property int|null $changement_id
 * @property-read Cellule $cellule
 */
class HistoriqueCellule extends Model
{
    use Indelebile;

    protected $table = 'historique_cellules';

    protected $fillable = ['membre_id', 'cellule_id', 'debut_le', 'fin_le', 'changement_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['debut_le' => 'date', 'fin_le' => 'date'];
    }

    /** @return BelongsTo<Cellule, $this> */
    public function cellule(): BelongsTo
    {
        return $this->belongsTo(Cellule::class);
    }
}
