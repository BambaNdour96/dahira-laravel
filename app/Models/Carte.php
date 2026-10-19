<?php

namespace App\Models;

use App\Enums\StatutCarte;
use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $membre_id
 * @property int|null $exercice_id
 * @property string $jeton
 * @property StatutCarte $statut
 * @property Carbon $emise_le
 * @property Carbon|null $revoquee_le
 * @property int|null $revoquee_par
 * @property string|null $motif_revocation
 * @property-read Membre $membre
 * @property-read Exercice|null $exercice
 */
class Carte extends Model
{
    use Indelebile, Journalise;

    protected $fillable = [
        'membre_id', 'exercice_id', 'jeton', 'statut', 'emise_le', 'revoquee_le', 'revoquee_par', 'motif_revocation',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'statut' => StatutCarte::class,
            'emise_le' => 'datetime',
            'revoquee_le' => 'datetime',
        ];
    }

    /** @return BelongsTo<Membre, $this> */
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    /**
     * L'exercice de la carte (« carte 2027 ») ; vide pour les cartes émises avant le renouvellement annuel.
     *
     * @return BelongsTo<Exercice, $this>
     */
    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }
}
