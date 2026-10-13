<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une action décidée en réunion : quoi, qui (un membre ou une commission), pour quand.
 *
 * @property int $id
 * @property int $instance_id
 * @property int|null $reunion_id
 * @property string $description
 * @property int|null $responsable_id
 * @property int|null $commission_id
 * @property Carbon|null $echeance
 * @property string $statut
 * @property-read Membre|null $responsable
 * @property-read CommissionComite|null $commission
 * @property-read Instance $instance
 */
class ActionComite extends Model
{
    public const STATUTS = ['a_faire' => 'À faire', 'en_cours' => 'En cours', 'faite' => 'Faite'];

    protected $table = 'actions_comite';

    protected $attributes = ['statut' => 'a_faire'];

    protected $fillable = ['instance_id', 'reunion_id', 'description', 'responsable_id', 'commission_id', 'echeance', 'statut'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['echeance' => 'date'];
    }

    protected static function booted(): void
    {
        static::creating(function (ActionComite $action): void {
            if ($action->reunion_id !== null && blank($action->instance_id)) {
                $action->instance_id = (int) Reunion::query()->whereKey($action->reunion_id)->value('instance_id');
            }
        });
    }

    public function responsableTexte(): string
    {
        return match (true) {
            $this->commission !== null => 'Commission '.$this->commission->nom,
            $this->responsable !== null => $this->responsable->prenom.' '.$this->responsable->nom,
            default => '—',
        };
    }

    /** @return BelongsTo<Membre, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Membre::class, 'responsable_id');
    }

    /** @return BelongsTo<CommissionComite, $this> */
    public function commission(): BelongsTo
    {
        return $this->belongsTo(CommissionComite::class, 'commission_id');
    }

    /** @return BelongsTo<Instance, $this> */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class);
    }
}
