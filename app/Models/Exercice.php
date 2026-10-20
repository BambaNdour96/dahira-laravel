<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un exercice : ses dates sont fixées par le super administrateur ; la clôture est préparée par le Jewrine finance et
 * confirmée par le Jewrine national. Clos, il ne se modifie plus et son bilan est figé.
 *
 * @property int $id
 * @property string $libelle
 * @property Carbon $date_debut
 * @property Carbon $date_fin
 * @property Carbon|null $cloture_preparee_le
 * @property int|null $cloture_preparee_par
 * @property Carbon|null $cloture_le
 * @property int|null $cloture_par
 * @property array<string, mixed>|null $bilan
 */
class Exercice extends Model
{
    use Journalise;

    protected $fillable = ['libelle', 'date_debut', 'date_fin', 'cloture_preparee_le', 'cloture_preparee_par', 'cloture_le', 'cloture_par', 'bilan'];

    public function estClos(): bool
    {
        return $this->cloture_le !== null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'cloture_preparee_le' => 'datetime',
            'cloture_le' => 'datetime',
            'bilan' => 'array',
        ];
    }

    /** Règle 4.3 : l'exercice en cours contient la date du jour. */
    public static function enCours(): ?self
    {
        $aujourdhui = now()->toDateString();

        return static::query()
            ->whereDate('date_debut', '<=', $aujourdhui)
            ->whereDate('date_fin', '>=', $aujourdhui)
            ->first();
    }

    /** @return HasMany<Tarif, $this> */
    public function tarifs(): HasMany
    {
        return $this->hasMany(Tarif::class);
    }
}
