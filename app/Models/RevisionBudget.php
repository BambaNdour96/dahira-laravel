<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une révision du budget d'une commission d'un comité d'organisation : l'ancien et le nouveau montant, le motif, qui.
 *
 * @property int $id
 * @property int $commission_comite_id
 * @property int|null $ancien
 * @property int $nouveau
 * @property string $motif
 * @property int $auteur_id
 * @property Carbon $created_at
 * @property-read CommissionComite $commission
 * @property-read User $auteur
 */
class RevisionBudget extends Model
{
    use Indelebile;

    protected $table = 'revisions_budget';

    protected $fillable = ['commission_comite_id', 'ancien', 'nouveau', 'motif', 'auteur_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['ancien' => 'integer', 'nouveau' => 'integer'];
    }

    /** @return BelongsTo<CommissionComite, $this> */
    public function commission(): BelongsTo
    {
        return $this->belongsTo(CommissionComite::class, 'commission_comite_id');
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
