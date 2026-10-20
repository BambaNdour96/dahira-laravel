<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La trace d'une correction d'un paiement validé : contre-passation, réaffectation, avoir ou remboursement.
 *
 * @property int $id
 * @property int $paiement_id
 * @property string $type
 * @property string $statut
 * @property int $montant
 * @property string $motif
 * @property int|null $nouveau_paiement_id
 * @property int|null $avoir_id
 * @property int|null $ordre_depense_id
 * @property int $auteur_id
 * @property-read Paiement $paiement
 * @property-read OrdreDepense|null $ordre
 */
class CorrectionPaiement extends Model
{
    use Indelebile, Journalise;

    public const TYPES = [
        'contre_passation' => 'Contre-passation', 'reaffectation' => 'Réaffectation', 'avoir' => 'Avoir', 'remboursement' => 'Remboursement',
    ];

    public const FAITE = 'faite';

    public const EN_COURS = 'en_cours';

    public const ABANDONNEE = 'abandonnee';

    protected $table = 'corrections_paiement';

    protected $attributes = ['statut' => self::FAITE];

    protected $fillable = ['paiement_id', 'type', 'statut', 'montant', 'motif', 'nouveau_paiement_id', 'avoir_id', 'ordre_depense_id', 'auteur_id'];

    /** @return BelongsTo<Paiement, $this> */
    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }

    /** @return BelongsTo<OrdreDepense, $this> */
    public function ordre(): BelongsTo
    {
        return $this->belongsTo(OrdreDepense::class, 'ordre_depense_id');
    }
}
