<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une entrée ou une sortie d'argent dans une caisse : un encaissement, une remise, plus tard une dépense ou une
 * correction. Rien ne s'efface : une erreur se corrige par un mouvement nouveau.
 *
 * @property int $id
 * @property int $caisse_id
 * @property string $sens
 * @property int $montant
 * @property string $nature
 * @property string $libelle
 * @property int|null $paiement_id
 * @property int|null $remise_id
 * @property int|null $participation_id
 * @property int|null $ordre_depense_id
 * @property int|null $avoir_id
 * @property int|null $auteur_id
 * @property Carbon $date_operation
 * @property-read Caisse $caisse
 * @property-read Paiement|null $paiement
 * @property-read ParticipationSympathisant|null $participation
 * @property-read OrdreDepense|null $ordreDepense
 * @property-read Avoir|null $avoir
 * @property-read User|null $auteur
 */
class MouvementCaisse extends Model
{
    use Indelebile;

    public const ENTREE = 'entree';

    public const SORTIE = 'sortie';

    public const NATURES = [
        'encaissement' => 'Encaissement', 'remise' => 'Remise d\'un représentant', 'depense' => 'Dépense',
        'correction' => 'Correction', 'reaffectation' => 'Réaffectation', 'ouverture' => 'Solde d\'ouverture',
        'retour' => 'Reste d\'avance rendu',
    ];

    protected $table = 'mouvements_caisse';

    protected $fillable = ['caisse_id', 'sens', 'montant', 'nature', 'libelle', 'paiement_id', 'remise_id', 'participation_id', 'ordre_depense_id', 'avoir_id', 'auteur_id', 'date_operation'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['date_operation' => 'datetime', 'montant' => 'integer'];
    }

    /** @return BelongsTo<Caisse, $this> */
    public function caisse(): BelongsTo
    {
        return $this->belongsTo(Caisse::class);
    }

    /** @return BelongsTo<Paiement, $this> */
    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }

    /** @return BelongsTo<ParticipationSympathisant, $this> */
    public function participation(): BelongsTo
    {
        return $this->belongsTo(ParticipationSympathisant::class, 'participation_id');
    }

    /** @return BelongsTo<OrdreDepense, $this> */
    public function ordreDepense(): BelongsTo
    {
        return $this->belongsTo(OrdreDepense::class);
    }

    /** @return BelongsTo<Avoir, $this> */
    public function avoir(): BelongsTo
    {
        return $this->belongsTo(Avoir::class);
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
