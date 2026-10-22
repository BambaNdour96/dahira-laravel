<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
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
 * @property int|null $commission_id
 * @property Carbon|null $mise_en_place
 * @property string $statut
 * @property int|null $cree_par
 * @property-read Membre|null $president
 * @property-read Collecte|null $collecte
 * @property bool $suivi_depenses
 * @property-read Commission|null $commission
 */
class Instance extends Model
{
    use Indelebile;

    public const COMITE_DIRECTEUR = 'comite_directeur';

    /** Un comité d'organisation dédié (le CDGMT pour un Magal, le Grand Ziar…) : le même espace pour tous. */
    public const COMITE_ORGANISATION = 'comite_organisation';

    public const COMMISSION = 'commission';

    /** Les types que l'on crée à l'écran ; l'espace d'une commission permanente se crée avec elle. */
    public const TYPES = [
        self::COMITE_DIRECTEUR => 'Comité directeur',
        self::COMITE_ORGANISATION => 'Comité d\'organisation (CDGMT, Grand Ziar…)',
    ];

    protected $attributes = ['statut' => 'active'];

    protected $fillable = ['type', 'nom', 'code', 'annee', 'collecte_id', 'commission_id', 'president_id', 'mise_en_place', 'statut', 'suivi_depenses', 'cree_par'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['mise_en_place' => 'date', 'annee' => 'integer', 'suivi_depenses' => 'boolean'];
    }

    public static function comiteDirecteur(): self
    {
        return self::query()->where('type', self::COMITE_DIRECTEUR)->firstOrFail();
    }

    public function estComiteDirecteur(): bool
    {
        return $this->type === self::COMITE_DIRECTEUR;
    }

    public function estComiteOrganisation(): bool
    {
        return $this->type === self::COMITE_ORGANISATION;
    }

    /** Le CDGMT : le comité d'organisation d'un Magal. */
    public function estCdgmt(): bool
    {
        return $this->estComiteOrganisation() && ($this->code === 'CDGMT' || $this->collecte?->type?->code === TypeProgramme::MAGAL);
    }

    /** Le Jewrine du comité de suivi des dépenses (nomination en cours), si le comité en a un. */
    public function jewrineSuiviDepenses(): ?User
    {
        return User::query()->whereIn('id', Nomination::query()->where('instance_id', $this->id)
            ->whereHas('poste', fn ($poste) => $poste->where('code', Poste::JEWRINE_SUIVI_DEPENSES))->select('user_id'))->first();
    }

    /** Le suppléant du Jewrine du suivi des dépenses (nomination en cours), s'il y en a un. */
    public function suppleantSuiviDepenses(): ?User
    {
        return User::query()->whereIn('id', Nomination::query()->where('instance_id', $this->id)
            ->whereHas('poste', fn ($poste) => $poste->where('code', Poste::SUPPLEANT_SUIVI_DEPENSES))->select('user_id'))->first();
    }

    /**
     * Les propositions du président pour le Jewrine du suivi des dépenses.
     *
     * @return HasMany<PropositionSuiviDepenses, $this>
     */
    public function propositionsSuivi(): HasMany
    {
        return $this->hasMany(PropositionSuiviDepenses::class);
    }

    /**
     * Les engagements de l'événement du comité (onglet Collecte).
     *
     * @return HasMany<Engagement, $this>
     */
    public function engagements(): HasMany
    {
        return $this->hasMany(Engagement::class, 'collecte_id', 'collecte_id');
    }

    /**
     * Les ordres de dépense de l'événement du comité (onglet Argent).
     *
     * @return HasMany<OrdreDepense, $this>
     */
    public function ordresDepense(): HasMany
    {
        return $this->hasMany(OrdreDepense::class, 'collecte_id', 'collecte_id');
    }

    /** L'espace d'une commission permanente. */
    public function estCommission(): bool
    {
        return $this->type === self::COMMISSION;
    }

    /** @return BelongsTo<Commission, $this> */
    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    /** @return BelongsToMany<Membre, $this> */
    public function membres(): BelongsToMany
    {
        return $this->belongsToMany(Membre::class, 'instance_membre')->withTimestamps();
    }

    /** @return HasMany<CommissionComite, $this> */
    public function commissions(): HasMany
    {
        return $this->hasMany(CommissionComite::class)->whereNull('archive_le')->orderBy('ordre');
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
