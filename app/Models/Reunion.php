<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Une réunion d'une instance : ordre du jour, présences, décisions, actions, puis PV publié.
 *
 * @property int $id
 * @property int $instance_id
 * @property string $titre
 * @property Carbon $debut
 * @property string $lieu
 * @property string|null $lien
 * @property int|null $secretaire_id
 * @property string|null $divers
 * @property string $statut
 * @property Carbon|null $convoquee_le
 * @property Carbon|null $publiee_le
 * @property int|null $publiee_par
 * @property-read Instance $instance
 * @property-read Membre|null $secretaire
 */
class Reunion extends Model
{
    protected $attributes = ['statut' => 'preparee'];

    protected $fillable = ['instance_id', 'titre', 'debut', 'lieu', 'lien', 'secretaire_id', 'divers', 'statut', 'convoquee_le', 'publiee_le', 'publiee_par'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['debut' => 'datetime', 'convoquee_le' => 'datetime', 'publiee_le' => 'datetime'];
    }

    public function estPubliee(): bool
    {
        return $this->statut === 'publiee';
    }

    /** @return BelongsTo<Instance, $this> */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class);
    }

    /** @return BelongsTo<Membre, $this> */
    public function secretaire(): BelongsTo
    {
        return $this->belongsTo(Membre::class, 'secretaire_id');
    }

    /** @return HasMany<PointOrdreDuJour, $this> */
    public function points(): HasMany
    {
        return $this->hasMany(PointOrdreDuJour::class)->orderBy('ordre');
    }

    /** @return HasMany<PresenceReunion, $this> */
    public function presences(): HasMany
    {
        return $this->hasMany(PresenceReunion::class);
    }

    /** @return HasMany<Decision, $this> */
    public function decisions(): HasMany
    {
        return $this->hasMany(Decision::class);
    }

    /** @return HasMany<ActionComite, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(ActionComite::class);
    }
}
