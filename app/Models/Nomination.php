<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use App\Models\Concerns\PrendFin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une nomination : une personne occupe un poste (dans une cellule pour un Jewrine de cellule),
 * depuis une date, désignée par quelqu'un. Elle prend fin sans jamais être effacée.
 * Seules les nominations en cours sont lues par défaut (voir PrendFin).
 *
 * @property int $id
 * @property int $user_id
 * @property int $poste_id
 * @property int|null $cellule_id
 * @property int|null $commission_id
 * @property int|null $instance_id
 * @property Carbon $debut_le
 * @property int|null $designe_par
 * @property string|null $motif_fin
 * @property-read User $user
 * @property-read Poste $poste
 * @property-read Cellule|null $cellule
 * @property-read Commission|null $commission
 * @property-read Instance|null $instance
 * @property-read User|null $designePar
 * @property-read User|null $finPar
 */
class Nomination extends Model
{
    use Indelebile, Journalise, PrendFin;

    protected $fillable = ['user_id', 'poste_id', 'cellule_id', 'commission_id', 'instance_id', 'debut_le', 'designe_par'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['debut_le' => 'date'];
    }

    /** « Jewrine de cellule (Kaolack) ». */
    public function libelle(): string
    {
        $lieu = $this->cellule->nom ?? $this->commission->nom ?? $this->instance->nom ?? null;

        return $this->poste->nom.($lieu !== null ? ' ('.$lieu.')' : '');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Poste, $this> */
    public function poste(): BelongsTo
    {
        return $this->belongsTo(Poste::class);
    }

    /** @return BelongsTo<Cellule, $this> */
    public function cellule(): BelongsTo
    {
        return $this->belongsTo(Cellule::class);
    }

    /** @return BelongsTo<Commission, $this> */
    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    /** @return BelongsTo<Instance, $this> */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class);
    }

    /** @return BelongsTo<User, $this> */
    public function designePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'designe_par');
    }

    /** @return BelongsTo<User, $this> */
    public function finPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fin_par');
    }
}
