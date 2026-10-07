<?php

namespace App\Models;

use App\Enums\MoyenPaiement;
use App\Enums\StatutPaiement;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $membre_id
 * @property string $objet
 * @property int $montant
 * @property int $nombre_mois
 * @property MoyenPaiement $moyen
 * @property string|null $reference
 * @property StatutPaiement $statut
 * @property string|null $motif_refus
 * @property Carbon $declare_le
 * @property Carbon|null $traite_le
 * @property int|null $traite_par
 */
class Paiement extends Model
{
    use Journalise;

    protected $fillable = [
        'membre_id', 'objet', 'montant', 'nombre_mois', 'moyen', 'reference', 'statut',
        'motif_refus', 'declare_le', 'traite_le', 'traite_par',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'statut' => StatutPaiement::class,
            'moyen' => MoyenPaiement::class,
            'montant' => 'integer',
            'nombre_mois' => 'integer',
            'declare_le' => 'datetime',
            'traite_le' => 'datetime',
        ];
    }

    /** Libellé lisible : « Frais de carte » ou « Cotisation (3 mois) ». */
    public function libelleObjet(): string
    {
        return $this->objet === 'cotisation'
            ? 'Cotisation ('.$this->nombre_mois.' mois)'
            : 'Frais de carte';
    }

    /** @return BelongsTo<Membre, $this> */
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    /** @return BelongsTo<User, $this> */
    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par');
    }
}
