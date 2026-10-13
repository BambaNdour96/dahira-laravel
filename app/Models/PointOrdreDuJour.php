<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un point de l'ordre du jour, avec le résumé des échanges.
 *
 * @property int $id
 * @property int $reunion_id
 * @property int $ordre
 * @property string $titre
 * @property string|null $resume
 */
class PointOrdreDuJour extends Model
{
    protected $table = 'reunion_points';

    protected $fillable = ['reunion_id', 'ordre', 'titre', 'resume'];

    /** @return BelongsTo<Reunion, $this> */
    public function reunion(): BelongsTo
    {
        return $this->belongsTo(Reunion::class);
    }
}
