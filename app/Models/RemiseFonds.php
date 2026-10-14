<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * La remise des espèces d'un représentant au Jewrine finance national, avec son bordereau.
 *
 * @property int $id
 * @property string $numero
 * @property int $cellule_id
 * @property int $representant_id
 * @property int $recue_par
 * @property int $montant
 * @property int $nombre_operations
 * @property Carbon $created_at
 * @property-read Cellule $cellule
 * @property-read User $representant
 * @property-read User $receveur
 */
class RemiseFonds extends Model
{
    use Indelebile;

    public const UPDATED_AT = null;

    protected $table = 'remises_fonds';

    protected $fillable = ['numero', 'cellule_id', 'representant_id', 'recue_par', 'montant', 'nombre_operations'];

    /** @return BelongsTo<Cellule, $this> */
    public function cellule(): BelongsTo
    {
        return $this->belongsTo(Cellule::class);
    }

    /** @return BelongsTo<User, $this> */
    public function representant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'representant_id');
    }

    /** @return BelongsTo<User, $this> */
    public function receveur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recue_par');
    }

    /** @return HasMany<Paiement, $this> */
    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class, 'remise_id');
    }
}
