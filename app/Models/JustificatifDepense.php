<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une pièce qui justifie une avance : un achat, son montant, sa photo (reçu, facture).
 *
 * @property int $id
 * @property int $ordre_depense_id
 * @property string $objet
 * @property int $montant
 * @property string $piece_path
 * @property int $ajoute_par
 * @property Carbon $created_at
 * @property-read OrdreDepense $ordre
 */
class JustificatifDepense extends Model
{
    use Indelebile, Journalise;

    protected $table = 'justificatifs_depense';

    protected $fillable = ['ordre_depense_id', 'objet', 'montant', 'piece_path', 'ajoute_par'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['montant' => 'integer'];
    }

    /** @return BelongsTo<OrdreDepense, $this> */
    public function ordre(): BelongsTo
    {
        return $this->belongsTo(OrdreDepense::class, 'ordre_depense_id');
    }
}
