<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Un savoir-faire, pour mobiliser les membres (cuisine, sonorisation, chauffeur…). La liste est tenue par le super
 * administrateur ; un savoir-faire qui n'a plus cours s'archive.
 *
 * @property int $id
 * @property string $nom
 * @property int $ordre
 */
class Competence extends Model
{
    use Archivable, Indelebile, Journalise;

    protected $fillable = ['nom', 'ordre'];

    /** @return BelongsToMany<Membre, $this> */
    public function membres(): BelongsToMany
    {
        return $this->belongsToMany(Membre::class)->withTimestamps();
    }
}
