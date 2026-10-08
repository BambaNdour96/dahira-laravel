<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * La présence d'un membre à un événement, enregistrée au scan de sa carte.
 *
 * @property int $id
 * @property int $evenement_id
 * @property int $membre_id
 * @property int|null $enregistree_par
 * @property Carbon $created_at
 * @property-read Evenement $evenement
 * @property-read Membre $membre
 * @property-read User|null $controleur
 */
class Presence extends Model
{
    protected $fillable = ['evenement_id', 'membre_id', 'enregistree_par'];

    /** @return BelongsTo<Evenement, $this> */
    public function evenement(): BelongsTo
    {
        return $this->belongsTo(Evenement::class);
    }

    /** @return BelongsTo<Membre, $this> */
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    /** @return BelongsTo<User, $this> */
    public function controleur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enregistree_par');
    }
}
