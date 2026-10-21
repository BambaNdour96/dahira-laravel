<?php

namespace App\Models;

use App\Enums\ModeCollecte;
use App\Enums\StatutCollecte;
use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Models\Concerns\Annulable;
use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Un événement de la Daara (Magal, Ziar, Prokhane, Gamou…) et sa collecte ; ou la collecte d'un projet (sans type).
 * Le super administrateur et le Jewrine national le créent et le règlent d'un seul formulaire ; le super administrateur
 * choisit la contribution (Jàyante ou Sass), le Jewrine national fixe le Sass, l'objectif et le budget.
 *
 * @property int $id
 * @property string $titre
 * @property int|null $type_programme_id
 * @property ModeCollecte|null $mode
 * @property int|null $montant
 * @property int|null $objectif
 * @property int|null $budget
 * @property string|null $organisation
 * @property bool $itineraire_transport
 * @property Carbon|null $debut
 * @property Carbon|null $fin
 * @property string|null $lieu
 * @property Carbon $ouverture
 * @property Carbon|null $date_limite
 * @property bool $obligatoire
 * @property bool $versement_apres_limite
 * @property bool $visible_membres
 * @property Carbon|null $jauge_visible_depuis
 * @property bool $rappels_actifs
 * @property StatutCollecte $statut
 * @property int|null $cellule_id
 * @property int|null $evenement_id
 * @property string|null $description
 * @property int|null $cree_par
 * @property-read Cellule|null $cellule
 * @property-read Evenement|null $evenement
 * @property-read TypeProgramme|null $type
 * @property-read Instance|null $comite
 */
class Collecte extends Model
{
    use Annulable, Indelebile, Journalise;

    public const COMITE = 'comite';

    public const COMMISSIONS = 'commissions';

    /** Les deux façons d'organiser un événement. */
    public const ORGANISATIONS = [
        self::COMITE => 'Comité d\'organisation dédié (Magal, Grand Ziar)',
        self::COMMISSIONS => 'Commissions permanentes choisies (Prokhane, Gamou, Laylatoul Qadr…)',
    ];

    /** Valeurs par défaut, aussi pour une collecte tout juste créée. */
    protected $attributes = [
        'obligatoire' => true,
        'versement_apres_limite' => true,
        'visible_membres' => false,
        'rappels_actifs' => true,
        'itineraire_transport' => false,
        'statut' => 'ouverte',
    ];

    protected $fillable = [
        'titre', 'type_programme_id', 'mode', 'montant', 'objectif', 'budget', 'organisation', 'itineraire_transport', 'debut', 'fin', 'lieu', 'ouverture', 'date_limite', 'obligatoire',
        'versement_apres_limite', 'visible_membres', 'jauge_visible_depuis', 'rappels_actifs', 'statut', 'cellule_id',
        'evenement_id', 'description', 'cree_par',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'mode' => ModeCollecte::class,
            'statut' => StatutCollecte::class,
            'montant' => 'integer',
            'objectif' => 'integer',
            'budget' => 'integer',
            'itineraire_transport' => 'boolean',
            'debut' => 'datetime',
            'fin' => 'datetime',
            'ouverture' => 'date',
            'date_limite' => 'date',
            'jauge_visible_depuis' => 'date',
            'obligatoire' => 'boolean',
            'versement_apres_limite' => 'boolean',
            'visible_membres' => 'boolean',
            'rappels_actifs' => 'boolean',
        ];
    }

    /** La contribution est réglée : le mode est choisi et, pour un Sass, son montant fixé. */
    public function contributionReglee(): bool
    {
        return $this->mode === ModeCollecte::Jayante || ($this->mode === ModeCollecte::Sass && $this->montant !== null && $this->montant > 0);
    }

    public function estSass(): bool
    {
        return $this->mode === ModeCollecte::Sass;
    }

    /** Ouverte aux versements aujourd'hui. */
    public function estOuverte(): bool
    {
        if ($this->statut !== StatutCollecte::Ouverte || $this->ouverture->isFuture() || ! $this->contributionReglee()) {
            return false;
        }

        return $this->versement_apres_limite || $this->date_limite === null || ! $this->date_limite->isPast();
    }

    public function estEnRetard(): bool
    {
        return $this->date_limite !== null && $this->date_limite->copy()->endOfDay()->isPast();
    }

    /** La jauge globale est montrée aux membres, si le bureau l'a décidé. */
    public function jaugeVisibleParLesMembres(): bool
    {
        return $this->visible_membres && ($this->jauge_visible_depuis === null || ! $this->jauge_visible_depuis->isFuture());
    }

    /**
     * Les membres concernés : membres validés, de toute la Daara ou d'une cellule.
     *
     * @return Builder<Membre>
     */
    public function membresConcernes(): Builder
    {
        return Membre::query()
            ->where('statut', StatutMembre::Valide->value)
            ->when($this->cellule_id !== null, fn (Builder $requete) => $requete->where('cellule_id', $this->cellule_id));
    }

    public function concerne(Membre $membre): bool
    {
        return $membre->statut === StatutMembre::Valide
            && ($this->cellule_id === null || $membre->cellule_id === $this->cellule_id);
    }

    /** Tout l'argent confirmé : versements des membres et participations des sympathisants. */
    public function totalVerse(): int
    {
        return (int) $this->paiements()->where('statut', StatutPaiement::Confirme->value)->sum('montant') + $this->totalSympathisants();
    }

    public function totalSympathisants(): int
    {
        return (int) ParticipationSympathisant::query()->where('collecte_id', $this->id)->sum('montant');
    }

    public function totalEngage(): int
    {
        return (int) $this->engagements()->sum('montant');
    }

    /** Montant de référence de la jauge : l'objectif, sinon le total des engagements. */
    public function cible(): int
    {
        return $this->objectif ?? $this->totalEngage();
    }

    public function taux(): int
    {
        $cible = $this->cible();

        return $cible > 0 ? (int) min(100, round($this->totalVerse() / $cible * 100)) : 0;
    }

    /** @return HasMany<Engagement, $this> */
    public function engagements(): HasMany
    {
        return $this->hasMany(Engagement::class);
    }

    /** @return HasMany<Paiement, $this> */
    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    /** @return BelongsTo<Cellule, $this> */
    public function cellule(): BelongsTo
    {
        return $this->belongsTo(Cellule::class);
    }

    /** @return BelongsTo<Evenement, $this> */
    public function evenement(): BelongsTo
    {
        return $this->belongsTo(Evenement::class);
    }

    /** @return BelongsTo<TypeProgramme, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeProgramme::class, 'type_programme_id');
    }

    /** Le type, pour l'affichage. */
    public function libelleType(): string
    {
        return $this->type->nom ?? 'Projet';
    }

    /** @return HasOne<Instance, $this> */
    public function comite(): HasOne
    {
        return $this->hasOne(Instance::class);
    }

    /**
     * Les commissions permanentes choisies (retirées comprises).
     *
     * @return HasMany<ProgrammeCommission, $this>
     */
    public function commissionsChoisies(): HasMany
    {
        return $this->hasMany(ProgrammeCommission::class);
    }

    /**
     * Les tâches confiées aux commissions pour cet événement.
     *
     * @return HasMany<ActionComite, $this>
     */
    public function taches(): HasMany
    {
        return $this->hasMany(ActionComite::class);
    }

    /**
     * Les événements de la liste des programmes (les collectes de projet en sont à part).
     *
     * @param  Builder<self>  $query
     */
    public function scopeEvenements(Builder $query): void
    {
        $query->whereNotNull($query->qualifyColumn('type_programme_id'));
    }
}
