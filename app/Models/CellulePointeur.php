<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une personne désignée pour pointer les présences d'une cellule : les frères ou les sœurs.
 *
 * @property int $id
 * @property int $cellule_id
 * @property int $user_id
 * @property string $perimetre
 * @property-read Cellule $cellule
 * @property-read User $user
 */
class CellulePointeur extends Model
{
    protected $table = 'cellule_pointeurs';

    protected $fillable = ['cellule_id', 'user_id', 'perimetre'];

    public const PERIMETRES = ['hommes' => 'Les frères', 'femmes' => 'Les sœurs'];

    /** @return BelongsTo<Cellule, $this> */
    public function cellule(): BelongsTo
    {
        return $this->belongsTo(Cellule::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
