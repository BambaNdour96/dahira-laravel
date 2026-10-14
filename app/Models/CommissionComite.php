<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une commission d'un comité (Cuisine, Hygiène et environnement, Finance…), avec son chef.
 *
 * @property int $id
 * @property int $instance_id
 * @property string $nom
 * @property string|null $mission
 * @property int|null $chef_id
 * @property int $ordre
 * @property-read Instance $instance
 * @property-read Membre|null $chef
 */
class CommissionComite extends Model
{
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

    /** Le budget : la somme des besoins retenus. */
    public function budget(): int
    {
        return (int) $this->besoins()->where('statut', 'retenu')->get()->sum(fn (BesoinCommission $besoin): int => $besoin->montant());
    }

    /** Ce qui a été dépensé : les dépenses validées par le Jewrine finance. */
    public function depense(): int
    {
        return (int) $this->depenses()->where('statut', 'validee')->sum('montant');
    }

    /** @return HasMany<ActionComite, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(ActionComite::class, 'commission_id');
    }
}
