<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * La proposition du président d'un comité d'organisation pour le Jewrine de son comité de suivi des dépenses, que le
 * Jewrine national valide (la nomination suit) ou refuse, avec un motif.
 *
 * @property int $id
 * @property int $instance_id
 * @property int $membre_id
 * @property string $poste
 * @property int $propose_par
 * @property string $statut
 * @property int|null $decide_par
 * @property Carbon|null $decide_le
 * @property string|null $motif
 * @property-read Instance $instance
 * @property-read Membre $membre
 * @property-read User $proposant
 */
class PropositionSuiviDepenses extends Model
{
    use Indelebile, Journalise;

    public const PROPOSEE = 'proposee';

    public const VALIDEE = 'validee';

    public const REFUSEE = 'refusee';

    public const LIBELLES = [self::PROPOSEE => 'À valider', self::VALIDEE => 'Validée', self::REFUSEE => 'Refusée'];

    protected $table = 'propositions_suivi_depenses';

    protected $attributes = ['statut' => self::PROPOSEE, 'poste' => Poste::JEWRINE_SUIVI_DEPENSES];

    public function pourSuppleant(): bool
    {
        return $this->poste === Poste::SUPPLEANT_SUIVI_DEPENSES;
    }

    protected $fillable = ['instance_id', 'membre_id', 'poste', 'propose_par', 'statut', 'decide_par', 'decide_le', 'motif'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['decide_le' => 'datetime'];
    }

    /** @return BelongsTo<Instance, $this> */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class);
    }

    /** @return BelongsTo<Membre, $this> */
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    /** @return BelongsTo<User, $this> */
    public function proposant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'propose_par');
    }
}
