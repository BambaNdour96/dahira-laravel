<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Trace d'une modification d'engagement : qui, quand, ancien et nouveau montant, motif.
 *
 * @property int $id
 * @property int $engagement_id
 * @property int|null $ancien
 * @property int $nouveau
 * @property string $motif
 * @property int|null $auteur_id
 * @property Carbon $created_at
 * @property-read User|null $auteur
 */
class HistoriqueEngagement extends Model
{
    use Indelebile;

    protected $table = 'historique_engagements';

    protected $fillable = ['engagement_id', 'ancien', 'nouveau', 'motif', 'auteur_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['ancien' => 'integer', 'nouveau' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
