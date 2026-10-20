<?php

namespace App\Models;

use App\Enums\TypeCaisse;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une caisse de la Daara : Wave, Orange Money, caisse générale, caisse d'un représentant, banque. Son solde est la somme
 * de ses mouvements ; une caisse qui n'a plus cours s'archive.
 *
 * @property int $id
 * @property string $nom
 * @property TypeCaisse $type
 * @property int|null $user_id
 * @property int $ordre
 * @property-read User|null $representant
 */
class Caisse extends Model
{
    use Archivable, Indelebile, Journalise;

    protected $fillable = ['nom', 'type', 'user_id', 'ordre'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['type' => TypeCaisse::class];
    }

    /** Le solde : entrées moins sorties. */
    public function solde(): int
    {
        return (int) $this->mouvements()->where('sens', MouvementCaisse::ENTREE)->sum('montant')
            - (int) $this->mouvements()->where('sens', MouvementCaisse::SORTIE)->sum('montant');
    }

    /** @return HasMany<MouvementCaisse, $this> */
    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementCaisse::class);
    }

    /** @return BelongsTo<User, $this> */
    public function representant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
