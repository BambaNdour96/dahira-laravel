<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Le lien d'un membre avec une commission permanente, de bout en bout : son souhait (à l'inscription),
 * la décision du Jewrine de la commission (accepté ou refusé), puis son départ. Rien ne s'efface.
 *
 * @property int $id
 * @property int $commission_id
 * @property int $membre_id
 * @property Carbon|null $souhaite_le
 * @property Carbon|null $debut_le
 * @property int|null $ajoute_par
 * @property Carbon|null $refuse_le
 * @property int|null $refuse_par
 * @property string|null $motif_refus
 * @property Carbon|null $fin_le
 * @property int|null $fin_par
 * @property string|null $motif_fin
 * @property-read Commission $commission
 * @property-read Membre $membre
 */
class AppartenanceCommission extends Model
{
    use Indelebile, Journalise;

    protected $table = 'appartenances_commission';

    protected $fillable = ['commission_id', 'membre_id', 'souhaite_le', 'debut_le', 'ajoute_par'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'souhaite_le' => 'datetime',
            'debut_le' => 'date',
            'refuse_le' => 'datetime',
            'fin_le' => 'datetime',
        ];
    }

    public function estSouhait(): bool
    {
        return $this->debut_le === null && $this->refuse_le === null && $this->fin_le === null;
    }

    public function estActive(): bool
    {
        return $this->debut_le !== null && $this->refuse_le === null && $this->fin_le === null;
    }

    /**
     * Les membres en cours.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActives(Builder $query): void
    {
        $query->whereNotNull('debut_le')->whereNull('refuse_le')->whereNull('fin_le');
    }

    /**
     * Les souhaits qui attendent la décision du Jewrine.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSouhaits(Builder $query): void
    {
        $query->whereNull('debut_le')->whereNull('refuse_le')->whereNull('fin_le');
    }

    /** @return BelongsTo<Commission, $this> */
    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    /** @return BelongsTo<Membre, $this> */
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }
}
