<?php

namespace App\Models;

use App\Enums\LienFamille;
use App\Enums\NiveauEtudes;
use App\Enums\Sexe;
use App\Enums\SituationProfessionnelle;
use App\Enums\StatutCarte;
use App\Enums\StatutMembre;
use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use App\Services\PhotoService;
use App\Services\TelephoneNormalizer;
use Database\Factories\MembreFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $responsable_id
 * @property LienFamille|null $lien_famille
 * @property int|null $inscrit_par
 * @property Carbon|null $compte_propose_le
 * @property string|null $origine
 * @property int|null $import_id
 * @property string|null $matricule
 * @property string $prenom
 * @property string $nom
 * @property Sexe $sexe
 * @property Carbon|null $date_naissance
 * @property string|null $niveau_etudes_autre
 * @property string|null $lieu_naissance
 * @property string|null $telephone
 * @property string|null $email
 * @property string|null $adresse
 * @property string|null $ville
 * @property string $pays_residence
 * @property int $cellule_id
 * @property string|null $photo_path
 * @property NiveauEtudes|null $niveau_etudes
 * @property string|null $diplome
 * @property string|null $domaine_etudes
 * @property list<string>|null $langues
 * @property SituationProfessionnelle|null $situation_professionnelle
 * @property string|null $profession
 * @property string|null $secteur_activite
 * @property StatutMembre $statut
 * @property string|null $motif_statut
 * @property Carbon|null $accepte_le
 * @property Carbon|null $date_adhesion
 * @property Carbon|null $valide_le
 * @property int|null $valide_par
 * @property Carbon|null $consentement_le
 * @property string|null $consentement_version
 * @property-read Cellule $cellule
 * @property-read Membre|null $responsable
 */
class Membre extends Model
{
    /** @use HasFactory<MembreFactory> */
    use HasFactory, Journalise;

    use Indelebile;

    /** Délai pour régler les frais de carte après l'acceptation. */
    public const DELAI_PAIEMENT_JOURS = 10;

    /** Champs pris en compte dans la barre « Profil complété ». */
    public const CHAMPS_PROFIL = [
        'email', 'adresse', 'ville', 'niveau_etudes', 'diplome', 'domaine_etudes',
        'langues', 'arabe_coran', 'situation_professionnelle', 'profession', 'secteur_activite',
    ];

    /**
     * Valeurs par défaut connues dès la création de l'objet.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'statut' => 'en_attente',
        'pays_residence' => 'Sénégal',
    ];

    protected $fillable = [
        'niveau_etudes_autre', 'user_id', 'prenom', 'nom', 'sexe', 'date_naissance', 'lieu_naissance', 'telephone', 'email',
        'adresse', 'ville', 'pays_residence', 'cellule_id', 'photo_path',
        'niveau_etudes', 'diplome', 'domaine_etudes', 'langues',
        'situation_professionnelle', 'profession', 'secteur_activite',
        'date_adhesion', 'consentement_le', 'consentement_version',
        'responsable_id', 'lien_famille', 'inscrit_par', 'compte_propose_le', 'origine', 'import_id',
    ];

    /** Le changement de cellule validé en cours d'application, noté dans l'historique (non enregistré en base). */
    public ?int $changementEnCours = null;

    /** Origine d'une fiche reprise des listes existantes. */
    public const ORIGINE_REPRISE = 'reprise';

    /** Âge de la majorité : en dessous, le membre est un enfant, sans compte de connexion. */
    public const AGE_MAJORITE = 18;

    protected static function booted(): void
    {
        // Un sympathisant qui s'inscrit avec le même numéro garde son historique de visites.
        static::created(function (Membre $membre): void {
            // Chaque membre ouvre son historique de cellules dès son inscription.
            HistoriqueCellule::query()->create(['membre_id' => $membre->id, 'cellule_id' => $membre->cellule_id, 'debut_le' => now()->toDateString()]);

            if ($membre->telephone === null) {
                return;
            }

            Sympathisant::query()->whereNull('membre_id')->where('telephone', $membre->telephone)->update(['membre_id' => $membre->id]);
        });

        // L'historique des cellules suit chaque changement : la période en cours se ferme, une nouvelle s'ouvre.
        static::updated(function (Membre $membre): void {
            if (! $membre->wasChanged('cellule_id')) {
                return;
            }

            HistoriqueCellule::query()->where('membre_id', $membre->id)->whereNull('fin_le')->update(['fin_le' => now()->toDateString()]);
            HistoriqueCellule::query()->create([
                'membre_id' => $membre->id,
                'cellule_id' => $membre->cellule_id,
                'debut_le' => now()->toDateString(),
                'changement_id' => $membre->changementEnCours,
            ]);
        });

        // Règle 4.6 : toute nouvelle photo est retraitée avant l'enregistrement.
        static::saving(function (Membre $membre): void {
            if (! $membre->isDirty('photo_path')) {
                return;
            }

            $photos = app(PhotoService::class);
            $ancienne = $membre->getRawOriginal('photo_path');

            if (is_string($membre->photo_path) && $membre->photo_path !== '') {
                $membre->photo_path = $photos->traiter($membre->photo_path);
            }

            if (is_string($ancienne) && $ancienne !== $membre->photo_path) {
                $photos->supprimer($ancienne);
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sexe' => Sexe::class,
            'statut' => StatutMembre::class,
            'niveau_etudes' => NiveauEtudes::class,
            'situation_professionnelle' => SituationProfessionnelle::class,
            'langues' => 'array',
            'date_naissance' => 'date',
            'date_adhesion' => 'date',
            'accepte_le' => 'datetime',
            'valide_le' => 'datetime',
            'consentement_le' => 'datetime',
            'lien_famille' => LienFamille::class,
            'compte_propose_le' => 'datetime',
        ];
    }

    /** Pourcentage du profil renseigné, pour la barre de progression. */
    public function completude(): int
    {
        $champs = array_values(array_filter(self::CHAMPS_PROFIL, fn (string $champ): bool => $this->champApplicable($champ)));
        $remplis = collect($champs)->filter(fn (string $champ): bool => $champ === 'arabe_coran'
            ? $this->profilArabeCoran?->estRemplie() === true
            : filled($this->getAttribute($champ)))->count();

        return (int) round($remplis / count($champs) * 100);
    }

    /** Un champ sans objet (diplôme sans études, profession d'un retraité) ne compte pas dans le profil. */
    public function champApplicable(string $champ): bool
    {
        return match ($champ) {
            'diplome', 'domaine_etudes' => $this->niveau_etudes !== NiveauEtudes::Aucun,
            'secteur_activite' => $this->situation_professionnelle !== SituationProfessionnelle::Retraite,
            'profession' => ! in_array($this->situation_professionnelle, [SituationProfessionnelle::Retraite, SituationProfessionnelle::EleveEtudiant], true),
            default => true,
        };
    }

    /** Un enfant : moins de 18 ans. Sans date de naissance, on ne le suppose pas enfant. */
    public function estEnfant(): bool
    {
        return $this->date_naissance !== null && $this->date_naissance->copy()->addYears(self::AGE_MAJORITE)->isFuture();
    }

    /** Un proche dont la fiche est gérée par un autre membre (enfant, ou adulte sans téléphone). */
    public function estGereParUnProche(): bool
    {
        return $this->responsable_id !== null;
    }

    /** Le numéro où le joindre : le sien, ou à défaut celui du membre qui gère sa fiche (sans le « + »). */
    public function numeroDeContact(): string
    {
        return ltrim($this->telephone ?? $this->responsable->telephone ?? '', '+');
    }

    /** Date limite pour régler les frais de carte. */
    public function dateLimitePaiement(): ?Carbon
    {
        return $this->accepte_le?->copy()->addDays(self::DELAI_PAIEMENT_JOURS);
    }

    /** @return BelongsTo<Cellule, $this> */
    public function cellule(): BelongsTo
    {
        return $this->belongsTo(Cellule::class);
    }

    /** Le premier poste en cours dans l'ordre des postes, affiché sur la carte du téléphone. */
    public function postePrincipal(): ?Poste
    {
        if ($this->user_id === null) {
            return null;
        }

        return Poste::query()
            ->whereIn('id', Nomination::query()->where('user_id', $this->user_id)->select('poste_id'))
            ->orderBy('ordre')
            ->first();
    }

    /**
     * Les commissions permanentes dont il est membre aujourd'hui.
     *
     * @return BelongsToMany<Commission, $this>
     */
    public function commissions(): BelongsToMany
    {
        return $this->belongsToMany(Commission::class, 'appartenances_commission')
            ->wherePivotNotNull('debut_le')
            ->wherePivotNull('refuse_le')
            ->wherePivotNull('fin_le');
    }

    /**
     * Ses souhaits, appartenances, refus et départs, toutes commissions confondues.
     *
     * @return HasMany<AppartenanceCommission, $this>
     */
    public function appartenances(): HasMany
    {
        return $this->hasMany(AppartenanceCommission::class);
    }

    /** @return HasMany<Carte, $this> */
    public function cartes(): HasMany
    {
        return $this->hasMany(Carte::class);
    }

    /** @return HasOne<Carte, $this> */
    public function carteActive(): HasOne
    {
        return $this->hasOne(Carte::class)->where('statut', StatutCarte::Active->value);
    }

    /** @return HasMany<Paiement, $this> */
    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    /**
     * Les commissions d'événement dont il est membre.
     *
     * @return BelongsToMany<CommissionComite, $this>
     */
    public function commissionsComite(): BelongsToMany
    {
        return $this->belongsToMany(CommissionComite::class, 'commission_comite_membre')->withTimestamps();
    }

    /**
     * Ses savoir-faire, pour être mobilisé.
     *
     * @return BelongsToMany<Competence, $this>
     */
    public function competences(): BelongsToMany
    {
        return $this->belongsToMany(Competence::class)->withTimestamps()->orderBy('ordre');
    }

    /**
     * Ses cellules successives, la plus récente d'abord.
     *
     * @return HasMany<HistoriqueCellule, $this>
     */
    public function historiqueCellules(): HasMany
    {
        return $this->hasMany(HistoriqueCellule::class)->latest('debut_le')->latest('id');
    }

    /**
     * Ses demandes de changement de cellule.
     *
     * @return HasMany<ChangementCellule, $this>
     */
    public function changementsCellule(): HasMany
    {
        return $this->hasMany(ChangementCellule::class);
    }

    /**
     * Sa rubrique « Arabe et Coran » (confidentielle).
     *
     * @return HasOne<ProfilArabeCoran, $this>
     */
    public function profilArabeCoran(): HasOne
    {
        return $this->hasOne(ProfilArabeCoran::class);
    }

    /** @return BelongsTo<Membre, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Membre::class, 'responsable_id');
    }

    /**
     * Les proches dont il gère la fiche.
     *
     * @return HasMany<Membre, $this>
     */
    public function proches(): HasMany
    {
        return $this->hasMany(Membre::class, 'responsable_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    /** @return Attribute<never, string> */
    protected function prenom(): Attribute
    {
        return Attribute::set(fn (string $valeur): string => Str::title(mb_strtolower(trim($valeur))));
    }

    /** @return Attribute<never, string> */
    protected function nom(): Attribute
    {
        return Attribute::set(fn (string $valeur): string => mb_strtoupper(trim($valeur)));
    }

    /** @return Attribute<string, never> */
    protected function nomComplet(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->prenom.' '.$this->nom));
    }

    /**
     * Facultatif (un enfant, un membre sans téléphone) ; toujours enregistré au format international.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function telephone(): Attribute
    {
        return Attribute::make(
            get: fn (?string $valeur): ?string => $valeur,
            set: fn (?string $valeur): ?string => blank($valeur) ? null : (TelephoneNormalizer::normaliser($valeur) ?? $valeur),
        );
    }
}
