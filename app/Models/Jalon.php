<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une étape d'un projet, avec sa date prévue et sa date de réalisation.
 *
 * @property int $id
 * @property int $projet_id
 * @property string $titre
 * @property Carbon $echeance
 * @property Carbon|null $realise_le
 * @property-read Projet $projet
 */
class Jalon extends Model
{
    use Archivable, Indelebile, Journalise;

    protected $fillable = ['projet_id', 'titre', 'echeance', 'realise_le'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['echeance' => 'date', 'realise_le' => 'date'];
    }

    public function etat(): string
    {
        return match (true) {
            $this->realise_le !== null => 'Réalisée',
            $this->echeance->copy()->endOfDay()->isPast() => 'En retard',
            default => 'À venir',
        };
    }

    /** @return BelongsTo<Projet, $this> */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(Projet::class);
    }
}
