<?php

namespace App\Models;

use App\Enums\ModeCollecte;
use App\Enums\ProgrammeCollecte;
use App\Enums\StatutCollecte;
use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Une collecte de la Daara : Magal, Ziar, Prokhane, programme secondaire ou projet.
 * Tous ses paramètres sont fixés par le super administrateur.
 *
 * @property int $id
 * @property string $titre
 * @property ProgrammeCollecte $programme
 * @property ModeCollecte $mode
 * @property int|null $montant
 * @property int|null $objectif
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
 */
class Collecte extends Model
{
    use Journalise;

    /** Valeurs par défaut, aussi pour une collecte tout juste créée. */
    protected $attributes = [
        'obligatoire' => true,
        'versement_apres_limite' => true,
        'visible_membres' => false,
        'rappels_actifs' => true,
        'statut' => 'ouverte',
    ];

    protected $fillable = [
        'titre', 'programme', 'mode', 'montant', 'objectif', 'ouverture', 'date_limite', 'obligatoire',
        'versement_apres_limite', 'visible_membres', 'jauge_visible_depuis', 'rappels_actifs', 'statut', 'cellule_id',
        'evenement_id', 'description', 'cree_par',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'programme' => ProgrammeCollecte::class,
            'mode' => ModeCollecte::class,
            'statut' => StatutCollecte::class,
            'montant' => 'integer',
            'objectif' => 'integer',
            'ouverture' => 'date',
            'date_limite' => 'date',
            'jauge_visible_depuis' => 'date',
            'obligatoire' => 'boolean',
            'versement_apres_limite' => 'boolean',
            'visible_membres' => 'boolean',
            'rappels_actifs' => 'boolean',
        ];
    }

    /** Ouverte aux versements aujourd'hui. */
    public function estOuverte(): bool
    {
        if ($this->statut !== StatutCollecte::Ouverte || $this->ouverture->isFuture()) {
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

    public function totalVerse(): int
    {
        return (int) $this->paiements()->where('statut', StatutPaiement::Confirme->value)->sum('montant');
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
}
