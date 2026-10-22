<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Une commission d'un comité (Cuisine, Hygiène et environnement, Finance…), avec son chef.
 *
 * @property int $id
 * @property int $instance_id
 * @property string $nom
 * @property string|null $mission
 * @property int|null $chef_id
 * @property int|null $budget_arrete
 * @property int $ordre
 * @property-read Instance $instance
 * @property-read Membre|null $chef
 */
class CommissionComite extends Model
{
    use Archivable, Indelebile, Journalise;

    protected $table = 'commissions_comite';

    protected $fillable = ['instance_id', 'nom', 'mission', 'chef_id', 'ordre'];

    /** @return BelongsTo<Instance, $this> */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class);
    }

    /** @return BelongsTo<Membre, $this> */
    public function chef(): BelongsTo
    {
        return $this->belongsTo(Membre::class, 'chef_id');
    }

    /** @return BelongsToMany<Membre, $this> */
    public function membres(): BelongsToMany
    {
        return $this->belongsToMany(Membre::class, 'commission_comite_membre')->withTimestamps();
    }

    /**
     * Les volontaires : propositions et invitations, avec leur décision.
     *
     * @return HasMany<Volontariat, $this>
     */
    public function volontariats(): HasMany
    {
        return $this->hasMany(Volontariat::class, 'commission_comite_id');
    }

    /** @return HasMany<BesoinCommission, $this> */
    public function besoins(): HasMany
    {
        return $this->hasMany(BesoinCommission::class, 'commission_id');
    }

    /** @return HasMany<DepenseCommission, $this> */
    public function depenses(): HasMany
    {
        return $this->hasMany(DepenseCommission::class, 'commission_id');
    }

    /** Le budget : celui arrêté par l'arbitrage ; à défaut, la somme des besoins retenus. */
    public function budget(): int
    {
        return $this->budget_arrete ?? $this->besoinsRetenus();
    }

    /** La somme des besoins retenus : la base proposée à l'arbitrage. */
    public function besoinsRetenus(): int
    {
        return (int) $this->besoins()->where('statut', 'retenu')->get()->sum(fn (BesoinCommission $besoin): int => $besoin->montant());
    }

    /** Ce qui a été dépensé : ce qui est sorti des caisses (restes d'avance rendus déduits), et les anciennes dépenses validées. */
    public function depense(): int
    {
        return $this->decaisse() + (int) $this->depenses()->where('statut', 'validee')->sum('montant');
    }

    /**
     * Les ordres de dépense payés de la commission.
     *
     * @return Collection<int, OrdreDepense>
     */
    private function ordresPayes(): Collection
    {
        return OrdreDepense::query()->where('commission_comite_id', $this->id)->where('statut', OrdreDepense::EXECUTE)->get();
    }

    /** Décaissé : ce que la commission a reçu ou fait payer, moins les restes d'avance rendus. */
    public function decaisse(): int
    {
        return (int) $this->ordresPayes()->sum(fn (OrdreDepense $ordre): int => $ordre->decaisse());
    }

    /** Justifié : les pièces des avances, et les paiements et remboursements (justifiés par leur preuve). */
    public function justifie(): int
    {
        return (int) $this->ordresPayes()->sum(fn (OrdreDepense $ordre): int => $ordre->justifie());
    }

    public function resteAJustifier(): int
    {
        return (int) $this->ordresPayes()->sum(fn (OrdreDepense $ordre): int => $ordre->resteAJustifier());
    }

    /**
     * Le bilan de la commission, rendu par son chef à la clôture du comité.
     *
     * @return HasOne<BilanComite, $this>
     */
    public function bilan(): HasOne
    {
        return $this->hasOne(BilanComite::class, 'commission_comite_id')->where('type', BilanComite::COMMISSION);
    }

    /**
     * Les révisions de son budget, de la plus récente à la plus ancienne.
     *
     * @return HasMany<RevisionBudget, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(RevisionBudget::class, 'commission_comite_id')->latest('id');
    }

    /** @return HasMany<ActionComite, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(ActionComite::class, 'commission_id');
    }
}
