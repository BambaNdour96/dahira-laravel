<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
