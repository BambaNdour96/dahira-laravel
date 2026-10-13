<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La présence d'un membre à une réunion : présent ou excusé.
 *
 * @property int $id
 * @property int $reunion_id
 * @property int $membre_id
 * @property string $statut
 * @property-read Membre $membre
 */
class PresenceReunion extends Model
{
    protected $table = 'reunion_presences';

    protected $fillable = ['reunion_id', 'membre_id', 'statut'];

    /** @return BelongsTo<Membre, $this> */
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }
}
