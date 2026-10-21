<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une commission permanente choisie pour organiser un événement, avec sa part du budget. Décochée, elle est retirée
 * (la date le dit), jamais effacée : ses tâches et ses dépenses restent.
 *
 * @property int $id
 * @property int $collecte_id
 * @property int $commission_id
 * @property int|null $budget
 * @property Carbon|null $retiree_le
 * @property-read Collecte $collecte
 * @property-read Commission $commission
 */
class ProgrammeCommission extends Model
{
    use Indelebile, Journalise;

    protected $table = 'programme_commissions';

    protected $fillable = ['collecte_id', 'commission_id', 'budget', 'retiree_le'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['budget' => 'integer', 'retiree_le' => 'datetime'];
    }

    /** @return BelongsTo<Collecte, $this> */
    public function collecte(): BelongsTo
    {
        return $this->belongsTo(Collecte::class);
    }

    /** @return BelongsTo<Commission, $this> */
    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }
}
