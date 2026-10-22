<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un bilan d'un comité d'organisation : celui d'une commission (par son chef), le bilan général (par le président) ou
 * l'évaluation de Suivi et Évaluation. Les chiffres sont figés au moment où il est rendu ; il se complète jusqu'à la
 * clôture du comité, et chaque modification est journalisée.
 *
 * @property int $id
 * @property int $instance_id
 * @property int|null $commission_comite_id
 * @property string $type
 * @property string|null $appreciation
 * @property string|null $forces
 * @property string|null $faiblesses
 * @property string|null $ameliorations
 * @property array<string, int>|null $chiffres
 * @property int $auteur_id
 * @property Carbon $updated_at
 * @property-read Instance $instance
 * @property-read CommissionComite|null $commission
 * @property-read User $auteur
 */
class BilanComite extends Model
{
    use Indelebile, Journalise;

    public const COMMISSION = 'commission';

    public const GENERAL = 'general';

    public const EVALUATION = 'evaluation';

    public const TYPES = [self::COMMISSION => 'Bilan de commission', self::GENERAL => 'Bilan général', self::EVALUATION => 'Évaluation de Suivi et Évaluation'];

    /** Les objectifs : atteints, en partie, ou non. */
    public const APPRECIATIONS = ['atteints' => 'Objectifs atteints', 'partiels' => 'Objectifs atteints en partie', 'non_atteints' => 'Objectifs non atteints'];

    protected $table = 'bilans_comite';

    protected $fillable = ['instance_id', 'commission_comite_id', 'type', 'appreciation', 'forces', 'faiblesses', 'ameliorations', 'chiffres', 'auteur_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['chiffres' => 'array'];
    }

    public function titre(): string
    {
        return $this->type === self::COMMISSION ? 'Commission '.($this->commission->nom ?? '—') : self::TYPES[$this->type];
    }

    /** @return BelongsTo<Instance, $this> */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class);
    }

    /** @return BelongsTo<CommissionComite, $this> */
    public function commission(): BelongsTo
    {
        return $this->belongsTo(CommissionComite::class, 'commission_comite_id');
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
