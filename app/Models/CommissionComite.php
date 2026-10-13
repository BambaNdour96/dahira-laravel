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

    /** @return HasMany<ActionComite, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(ActionComite::class, 'commission_id');
    }
}
