<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;

/**
 * Un libellé de dépense (achats de vivres, location, transport…), créé librement par la Finance ; il s'archive.
 *
 * @property int $id
 * @property string $nom
 * @property int $ordre
 */
class LibelleDepense extends Model
{
    use Archivable, Indelebile, Journalise;

    protected $table = 'libelles_depense';

    protected $fillable = ['nom', 'ordre'];
}
