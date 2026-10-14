<?php

namespace App\Models;

use App\Enums\MoyenPaiement;
use App\Models\Concerns\Indelebile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * La participation volontaire d'un sympathisant à une collecte, avec son reçu.
 *
 * @property int $id
 * @property int $collecte_id
 * @property int $sympathisant_id
 * @property int $montant
 * @property MoyenPaiement $moyen
 * @property string $numero_recu
 * @property int|null $enregistre_par
 * @property Carbon $created_at
 * @property-read Collecte $collecte
 * @property-read Sympathisant $sympathisant
 * @property-read User|null $auteur
 */
class ParticipationSympathisant extends Model
{
    use Indelebile;

    public const UPDATED_AT = null;

    protected $table = 'participations_sympathisants';

    protected $fillable = ['collecte_id', 'sympathisant_id', 'montant', 'moyen', 'numero_recu', 'enregistre_par'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['moyen' => MoyenPaiement::class];
    }

    /** @return BelongsTo<Collecte, $this> */
    public function collecte(): BelongsTo
    {
        return $this->belongsTo(Collecte::class);
    }

    /** @return BelongsTo<Sympathisant, $this> */
    public function sympathisant(): BelongsTo
    {
        return $this->belongsTo(Sympathisant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enregistre_par');
    }
}
