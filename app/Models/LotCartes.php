<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un lot de cartes en plastique pour l'imprimeur : préparé, envoyé, puis reçu.
 *
 * @property int $id
 * @property string $numero
 * @property int|null $cellule_id
 * @property string $statut
 * @property int $prepare_par
 * @property Carbon|null $envoye_le
 * @property Carbon|null $recu_le
 * @property Carbon $created_at
 * @property-read Cellule|null $cellule
 * @property-read User $preparateur
 */
class LotCartes extends Model
{
    use Indelebile, Journalise;

    public const PREPARE = 'prepare';

    public const ENVOYE = 'envoye';

    public const RECU = 'recu';

    public const LIBELLES = [self::PREPARE => 'Préparé', self::ENVOYE => 'Envoyé à l\'imprimeur', self::RECU => 'Reçu'];

    protected $table = 'lots_cartes';

    protected $attributes = ['statut' => self::PREPARE];

    protected $fillable = ['numero', 'cellule_id', 'statut', 'prepare_par', 'envoye_le', 'recu_le'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['envoye_le' => 'datetime', 'recu_le' => 'datetime'];
    }

    /** @return BelongsTo<Cellule, $this> */
    public function cellule(): BelongsTo
    {
        return $this->belongsTo(Cellule::class);
    }

    /** @return BelongsTo<User, $this> */
    public function preparateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepare_par');
    }

    /** @return HasMany<CartePhysique, $this> */
    public function cartes(): HasMany
    {
        return $this->hasMany(CartePhysique::class, 'lot_id');
    }
}
