<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une demande de dispense de cotisation : par le Jewrine de cellule, décidée par le Jewrine national.
 *
 * @property int $id
 * @property int $membre_id
 * @property int $nombre_mois
 * @property string $motif
 * @property int $demandee_par
 * @property string $statut
 * @property Carbon|null $decide_le
 * @property int|null $decide_par
 * @property string|null $motif_refus
 * @property Carbon $created_at
 * @property-read Membre $membre
 * @property-read User $demandeur
 */
class DemandeDispense extends Model
{
    use Indelebile, Journalise;

    public const EN_ATTENTE = 'en_attente';

    public const VALIDEE = 'validee';

    public const REFUSEE = 'refusee';

    public const LIBELLES = [self::EN_ATTENTE => 'En attente', self::VALIDEE => 'Validée', self::REFUSEE => 'Refusée'];

    protected $table = 'demandes_dispense';

    protected $attributes = ['statut' => self::EN_ATTENTE];

    protected $fillable = ['membre_id', 'nombre_mois', 'motif', 'demandee_par', 'statut', 'decide_le', 'decide_par', 'motif_refus'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['decide_le' => 'datetime', 'nombre_mois' => 'integer'];
    }

    /** @return BelongsTo<Membre, $this> */
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    /** @return BelongsTo<User, $this> */
    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demandee_par');
    }
}
