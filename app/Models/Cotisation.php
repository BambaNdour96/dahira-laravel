<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un mois de cotisation réglé (ou exonéré) par un membre.
 *
 * @property int $id
 * @property int $membre_id
 * @property Carbon $mois
 * @property int $montant
 * @property int|null $paiement_id
 * @property bool $exoneree
 * @property string|null $motif
 * @property-read Membre $membre
 * @property-read Paiement|null $paiement
 */
class Cotisation extends Model
{
    use Indelebile, Journalise;

    protected $fillable = ['membre_id', 'mois', 'montant', 'paiement_id', 'exoneree', 'motif'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'mois' => 'date',
            'montant' => 'integer',
            'exoneree' => 'boolean',
        ];
    }

    /** @return BelongsTo<Membre, $this> */
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    /** @return BelongsTo<Paiement, $this> */
    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }
}
