<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ce que l'on mesure dans un projet : une cible et la valeur atteinte.
 *
 * @property int $id
 * @property int $projet_id
 * @property string $libelle
 * @property string|null $unite
 * @property float $cible
 * @property float $valeur
 */
class Indicateur extends Model
{
    protected $fillable = ['projet_id', 'libelle', 'unite', 'cible', 'valeur'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['cible' => 'float', 'valeur' => 'float'];
    }

    public function atteinte(): int
    {
        return $this->cible > 0 ? (int) min(100, round($this->valeur / $this->cible * 100)) : 0;
    }

    /** @return BelongsTo<Projet, $this> */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(Projet::class);
    }
}
