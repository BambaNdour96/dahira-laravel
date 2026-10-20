<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un avoir : un crédit sur le compte d'un membre, né d'un paiement validé ; la Finance l'impute à la main sur un
 * paiement (cotisation, Jàyante), avec une trace.
 *
 * @property int $id
 * @property int $membre_id
 * @property int $montant
 * @property int $reste
 * @property string $motif
 * @property int|null $paiement_origine_id
 * @property int $auteur_id
 * @property-read Membre $membre
 * @property-read Paiement|null $origine
 */
class Avoir extends Model
{
    use Indelebile, Journalise;

    protected $fillable = ['membre_id', 'montant', 'reste', 'motif', 'paiement_origine_id', 'auteur_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['montant' => 'integer', 'reste' => 'integer'];
    }

    /** @return BelongsTo<Membre, $this> */
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    /** @return BelongsTo<Paiement, $this> */
    public function origine(): BelongsTo
    {
        return $this->belongsTo(Paiement::class, 'paiement_origine_id');
    }

    /** @return HasMany<Paiement, $this> */
    public function imputations(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }
}
