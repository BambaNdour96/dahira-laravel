<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Database\Factories\CommissionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Une commission permanente de la Daara (Administration, Suivi et Évaluation, Communication…).
 * Son Jewrine (nomination « Jewrine de commission ») ajoute ses membres et accepte ou refuse les souhaits
 * exprimés à l'inscription ; son espace est un comité lié (réunions, PV, décisions, tâches).
 *
 * @property int $id
 * @property string $nom
 * @property string|null $code
 * @property string|null $description
 * @property bool $actif
 * @property-read Instance|null $comite
 */
class Commission extends Model
{
    /** @use HasFactory<CommissionFactory> */
    use HasFactory, Journalise;

    /** Un membre exprime un souhait pour une commission à son inscription. */
    public const MAX_PAR_MEMBRE = 1;

    /** La commission Suivi et Évaluation : ses membres ont la casquette du même nom. */
    public const SUIVI_EVALUATION = 'suivi_evaluation';

    /** La commission Culturelle : ses membres voient le recensement Arabe et Coran. */
    public const CULTURELLE = 'culturelle';

    protected $fillable = ['nom', 'description', 'actif'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    /**
     * Les membres en cours de la commission.
     *
     * @return BelongsToMany<Membre, $this>
     */
    public function membres(): BelongsToMany
    {
        return $this->belongsToMany(Membre::class, 'appartenances_commission')
            ->wherePivotNotNull('debut_le')
            ->wherePivotNull('refuse_le')
            ->wherePivotNull('fin_le');
    }

    /**
     * Tout l'historique : souhaits, membres, refus, départs.
     *
     * @return HasMany<AppartenanceCommission, $this>
     */
    public function appartenances(): HasMany
    {
        return $this->hasMany(AppartenanceCommission::class);
    }

    /**
     * L'espace de la commission : un comité lié.
     *
     * @return HasOne<Instance, $this>
     */
    public function comite(): HasOne
    {
        return $this->hasOne(Instance::class);
    }

    /** Le Jewrine de la commission (nomination en cours), s'il y en a un. */
    public function jewrine(): ?User
    {
        return User::query()->whereIn('id', Nomination::query()
            ->where('commission_id', $this->id)
            ->whereHas('poste', fn (Builder $poste) => $poste->where('code', Poste::JEWRINE_COMMISSION))
            ->select('user_id'))->first();
    }

    /**
     * Les commissions dont cette personne est le Jewrine.
     *
     * @param  Builder<self>  $query
     */
    public function scopeDirigeesPar(Builder $query, User $utilisateur): void
    {
        $query->whereIn($query->qualifyColumn('id'), Nomination::query()
            ->where('user_id', $utilisateur->id)
            ->whereNotNull('commission_id')
            ->whereHas('poste', fn (Builder $poste) => $poste->where('code', Poste::JEWRINE_COMMISSION))
            ->select('commission_id'));
    }
}
