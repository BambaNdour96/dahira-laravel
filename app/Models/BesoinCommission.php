<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un besoin exprimé par une commission : article, quantité, prix estimé ; retenu ou écarté par le président.
 *
 * @property int $id
 * @property int $commission_id
 * @property string $designation
 * @property int $quantite
 * @property int $prix_unitaire
 * @property string $statut
 * @property string|null $motif
 * @property int|null $propose_par
 * @property int|null $traite_par
 * @property-read CommissionComite $commission
 */
class BesoinCommission extends Model
{
    use Indelebile;

    public const STATUTS = ['propose' => 'Proposé', 'retenu' => 'Retenu', 'ecarte' => 'Écarté'];

    protected $table = 'besoins_commission';

    protected $attributes = ['statut' => 'propose', 'quantite' => 1];

    protected $fillable = ['commission_id', 'designation', 'quantite', 'prix_unitaire', 'statut', 'motif', 'propose_par', 'traite_par'];

    public function montant(): int
    {
        return $this->quantite * $this->prix_unitaire;
    }

    /** @return BelongsTo<CommissionComite, $this> */
    public function commission(): BelongsTo
    {
        return $this->belongsTo(CommissionComite::class, 'commission_id');
    }
}
