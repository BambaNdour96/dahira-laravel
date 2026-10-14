<?php

namespace App\Models;

use App\Enums\NiveauArabe;
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
 * @property string|null $matricule
 * @property string $prenom
 * @property string $nom
 * @property Sexe $sexe
 * @property Carbon|null $date_naissance
 * @property string|null $niveau_etudes_autre
 * @property string|null $lieu_naissance
 * @property string $telephone
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
 * @property NiveauArabe|null $niveau_arabe
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
        'langues', 'niveau_arabe', 'situation_professionnelle', 'profession', 'secteur_activite',
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
        'niveau_etudes', 'diplome', 'domaine_etudes', 'langues', 'niveau_arabe',
        'situation_professionnelle', 'profession', 'secteur_activite',
        'date_adhesion', 'consentement_le', 'consentement_version',
    ];

    protected static function booted(): void
    {
        // Un sympathisant qui s'inscrit avec le même numéro garde son historique de visites.
        static::created(function (Membre $membre): void {
            Sympathisant::query()->whereNull('membre_id')->where('telephone', $membre->telephone)->update(['membre_id' => $membre->id]);
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
            'niveau_arabe' => NiveauArabe::class,
            'situation_professionnelle' => SituationProfessionnelle::class,
            'langues' => 'array',
            'date_naissance' => 'date',
            'date_adhesion' => 'date',
            'accepte_le' => 'datetime',
            'valide_le' => 'datetime',
            'consentement_le' => 'datetime',
        ];
    }

    /** Pourcentage du profil renseigné, pour la barre de progression. */
    public function completude(): int
    {
        $champs = array_values(array_filter(self::CHAMPS_PROFIL, fn (string $champ): bool => $this->champApplicable($champ)));
        $remplis = collect($champs)->filter(fn (string $champ): bool => filled($this->getAttribute($champ)))->count();

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

    /** @return Attribute<never, string> */
    protected function telephone(): Attribute
    {
        return Attribute::set(fn (string $valeur): string => TelephoneNormalizer::normaliser($valeur) ?? $valeur);
    }
}
