<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une décision, numérotée automatiquement : CDGMT-2026-D-001, CD-2026-D-004…
 *
 * @property int $id
 * @property int $instance_id
 * @property int $reunion_id
 * @property string $numero
 * @property string $texte
 * @property int|null $pour
 * @property int|null $contre
 * @property int|null $abstention
 * @property string $etat
 * @property-read Instance $instance
 * @property-read Reunion $reunion
 */
class Decision extends Model
{
    use Indelebile;

    public const ETATS = ['en_vigueur' => 'En vigueur', 'modifiee' => 'Modifiée', 'annulee' => 'Annulée'];

    protected $attributes = ['etat' => 'en_vigueur'];

    protected $fillable = ['instance_id', 'reunion_id', 'numero', 'texte', 'pour', 'contre', 'abstention', 'etat'];

    protected static function booted(): void
    {
        static::creating(function (Decision $decision): void {
            $reunion = Reunion::query()->with('instance')->findOrFail($decision->reunion_id);
            $decision->instance_id = $reunion->instance_id;

            if (blank($decision->numero)) {
                $prefixe = $reunion->instance->code.'-'.$reunion->debut->format('Y').'-D-';
                $rang = self::query()->where('numero', 'like', $prefixe.'%')->count() + 1;
                $decision->numero = $prefixe.str_pad((string) $rang, 3, '0', STR_PAD_LEFT);
            }
        });
    }

    public function vote(): ?string
    {
        if ($this->pour === null && $this->contre === null && $this->abstention === null) {
            return null;
        }

        return 'Pour : '.(int) $this->pour.' · Contre : '.(int) $this->contre.' · Abstention : '.(int) $this->abstention;
    }

    /** @return BelongsTo<Instance, $this> */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class);
    }

    /** @return BelongsTo<Reunion, $this> */
    public function reunion(): BelongsTo
    {
        return $this->belongsTo(Reunion::class);
    }
}
