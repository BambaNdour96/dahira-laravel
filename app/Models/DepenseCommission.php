<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une dépense d'une commission, déclarée par son chef et validée par le Jewrine finance.
 *
 * @property int $id
 * @property int $commission_id
 * @property string $objet
 * @property int $montant
 * @property Carbon $date_depense
 * @property string|null $fournisseur
 * @property string|null $justificatif_path
 * @property string $statut
 * @property string|null $motif
 * @property int|null $declaree_par
 * @property int|null $traitee_par
 * @property Carbon|null $traitee_le
 * @property-read CommissionComite $commission
 * @property-read User|null $declarant
 */
class DepenseCommission extends Model
{
    use Indelebile;

    public const STATUTS = ['declaree' => 'À valider', 'validee' => 'Validée', 'refusee' => 'Refusée'];

    protected $table = 'depenses_commission';

    protected $attributes = ['statut' => 'declaree'];

    protected $fillable = ['commission_id', 'objet', 'montant', 'date_depense', 'fournisseur', 'justificatif_path', 'statut', 'motif', 'declaree_par', 'traitee_par', 'traitee_le'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['date_depense' => 'date', 'traitee_le' => 'datetime'];
    }

    /** @return BelongsTo<CommissionComite, $this> */
    public function commission(): BelongsTo
    {
        return $this->belongsTo(CommissionComite::class, 'commission_id');
    }

    /** @return BelongsTo<User, $this> */
    public function declarant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declaree_par');
    }
}
