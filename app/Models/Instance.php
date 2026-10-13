<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Une instance de la Daara : le Comité directeur, un CDGMT ou un autre comité.
 *
 * @property int $id
 * @property string $type
 * @property string $nom
 * @property string $code
 * @property int $annee
 * @property int|null $collecte_id
 * @property int|null $president_id
 * @property int $quorum
 * @property Carbon|null $mise_en_place
 * @property string $statut
 * @property int|null $cree_par
 * @property-read Membre|null $president
 * @property-read Collecte|null $collecte
 */
class Instance extends Model
{
    public const COMITE_DIRECTEUR = 'comite_directeur';

    public const CDGMT = 'cdgmt';

    public const TYPES = [
        self::COMITE_DIRECTEUR => 'Comité directeur',
        self::CDGMT => 'CDGMT (Comité d\'organisation du Grand Magal de Touba)',
        'autre' => 'Autre comité',
    ];

    protected $attributes = ['quorum' => 50, 'statut' => 'active'];

    protected $fillable = ['type', 'nom', 'code', 'annee', 'collecte_id', 'president_id', 'quorum', 'mise_en_place', 'statut', 'cree_par'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['mise_en_place' => 'date', 'annee' => 'integer', 'quorum' => 'integer'];
    }

    public static function comiteDirecteur(): self
    {
        return self::query()->where('type', self::COMITE_DIRECTEUR)->firstOrFail();
    }

    public function estComiteDirecteur(): bool
    {
        return $this->type === self::COMITE_DIRECTEUR;
    }

    /** @return BelongsToMany<Membre, $this> */
    public function membres(): BelongsToMany
    {
        return $this->belongsToMany(Membre::class, 'instance_membre')->withTimestamps();
    }

    /** @return HasMany<CommissionComite, $this> */
    public function commissions(): HasMany
    {
        return $this->hasMany(CommissionComite::class)->orderBy('ordre');
    }

    /** @return HasMany<Reunion, $this> */
    public function reunions(): HasMany
    {
        return $this->hasMany(Reunion::class);
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

    /** La date du rendez-vous visé (le jour J du programme lié), pour le compte à rebours. */
    public function dateCible(): ?Carbon
    {
        return $this->collecte?->evenement?->debut;
    }

    /** @return BelongsTo<Membre, $this> */
    public function president(): BelongsTo
    {
        return $this->belongsTo(Membre::class, 'president_id');
    }

    /** @return BelongsTo<Collecte, $this> */
    public function collecte(): BelongsTo
    {
        return $this->belongsTo(Collecte::class);
    }
}
