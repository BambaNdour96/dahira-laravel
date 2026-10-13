<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * La venue d'un sympathisant à une séance ou à un jour J.
 *
 * @property int $id
 * @property int $evenement_id
 * @property int $sympathisant_id
 * @property int|null $enregistree_par
 * @property Carbon $created_at
 * @property-read Sympathisant $sympathisant
 * @property-read Evenement $evenement
 */
class VisiteSympathisant extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'visites_sympathisants';

    protected $fillable = ['evenement_id', 'sympathisant_id', 'enregistree_par'];

    /** @return BelongsTo<Sympathisant, $this> */
    public function sympathisant(): BelongsTo
    {
        return $this->belongsTo(Sympathisant::class);
    }

    /** @return BelongsTo<Evenement, $this> */
    public function evenement(): BelongsTo
    {
        return $this->belongsTo(Evenement::class);
    }
}
