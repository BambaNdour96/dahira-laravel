<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exercice extends Model
{
    use Journalise;

    protected $fillable = ['libelle', 'date_debut', 'date_fin'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
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
