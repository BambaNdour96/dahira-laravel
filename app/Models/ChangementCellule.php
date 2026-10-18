<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une demande de changement de cellule : par le membre ou son Jewrine, validée par le super administrateur.
 *
 * @property int $id
 * @property int $membre_id
 * @property int $cellule_depart_id
 * @property int $cellule_arrivee_id
 * @property string|null $motif
 * @property int $demande_par
 * @property string $statut
 * @property Carbon|null $decide_le
 * @property int|null $decide_par
 * @property string|null $motif_refus
 * @property Carbon $created_at
 * @property-read Membre $membre
 * @property-read Cellule $depart
 * @property-read Cellule $arrivee
 * @property-read User $demandeur
 */
class ChangementCellule extends Model
{
    use Indelebile, Journalise;

    public const EN_ATTENTE = 'en_attente';

    public const VALIDE = 'valide';

    public const REFUSE = 'refuse';

    public const RETIRE = 'retire';

    public const LIBELLES = [self::EN_ATTENTE => 'En attente', self::VALIDE => 'Validé', self::REFUSE => 'Refusé', self::RETIRE => 'Retiré'];

    protected $table = 'changements_cellule';

    protected $attributes = ['statut' => self::EN_ATTENTE];

    protected $fillable = ['membre_id', 'cellule_depart_id', 'cellule_arrivee_id', 'motif', 'demande_par', 'statut', 'decide_le', 'decide_par', 'motif_refus'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['decide_le' => 'datetime'];
    }

    /** @return BelongsTo<Membre, $this> */
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    /** @return BelongsTo<Cellule, $this> */
    public function depart(): BelongsTo
    {
        return $this->belongsTo(Cellule::class, 'cellule_depart_id');
    }

    /** @return BelongsTo<Cellule, $this> */
    public function arrivee(): BelongsTo
    {
        return $this->belongsTo(Cellule::class, 'cellule_arrivee_id');
    }

    /** @return BelongsTo<User, $this> */
    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demande_par');
    }
}
