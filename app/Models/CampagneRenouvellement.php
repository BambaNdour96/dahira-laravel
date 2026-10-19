<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * La campagne de renouvellement d'un exercice : ouverte par le super administrateur, avec sa date limite.
 *
 * @property int $id
 * @property int $exercice_id
 * @property Carbon $date_limite
 * @property Carbon $ouverte_le
 * @property int $ouverte_par
 * @property Carbon|null $relance_30_le
 * @property Carbon|null $relance_7_le
 * @property Carbon|null $relance_1_le
 * @property Carbon|null $cloturee_le
 * @property-read Exercice $exercice
 */
class CampagneRenouvellement extends Model
{
    use Indelebile, Journalise;

    protected $table = 'campagnes_renouvellement';

    protected $fillable = ['exercice_id', 'date_limite', 'ouverte_le', 'ouverte_par', 'relance_30_le', 'relance_7_le', 'relance_1_le', 'cloturee_le'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date_limite' => 'date', 'ouverte_le' => 'datetime', 'cloturee_le' => 'datetime',
            'relance_30_le' => 'datetime', 'relance_7_le' => 'datetime', 'relance_1_le' => 'datetime',
        ];
    }

    /** La campagne en cours : ouverte, pas encore close à sa date limite. */
    public static function ouverte(): ?self
    {
        return self::query()->with('exercice')->whereNull('cloturee_le')->latest('ouverte_le')->first();
    }

    public function estClose(): bool
    {
        return $this->cloturee_le !== null;
    }

    /** @return BelongsTo<Exercice, $this> */
    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    /** @return HasMany<Renouvellement, $this> */
    public function renouvellements(): HasMany
    {
        return $this->hasMany(Renouvellement::class, 'campagne_id');
    }
}
