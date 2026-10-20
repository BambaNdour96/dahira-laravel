<?php

namespace App\Models;

use App\Enums\ResultatProjet;
use App\Enums\SanteProjet;
use App\Enums\StatutProjet;
use App\Enums\TypeSuivi;
use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un projet de la Daara, suivi par la commission Suivi et Évaluation.
 *
 * @property int $id
 * @property string $titre
 * @property string $objectif
 * @property string|null $description
 * @property int|null $commission_id
 * @property int|null $responsable_id
 * @property Carbon $date_debut
 * @property Carbon $date_fin
 * @property int|null $budget
 * @property int|null $collecte_id
 * @property StatutProjet $statut
 * @property ResultatProjet|null $resultat
 * @property string|null $bilan
 * @property string|null $lecons
 * @property int|null $cree_par
 * @property-read Commission|null $commission
 * @property-read Membre|null $responsable
 * @property-read Collecte|null $collecte
 */
class Projet extends Model
{
    use Indelebile, Journalise;

    protected $attributes = ['statut' => 'en_cours'];

    protected $fillable = [
        'titre', 'objectif', 'description', 'commission_id', 'responsable_id', 'date_debut', 'date_fin',
        'budget', 'collecte_id', 'statut', 'resultat', 'bilan', 'lecons', 'cree_par',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'statut' => StatutProjet::class,
            'resultat' => ResultatProjet::class,
            'date_debut' => 'date',
            'date_fin' => 'date',
            'budget' => 'integer',
        ];
    }

    /** Avancement : la part des étapes réalisées. */
    public function avancement(): int
    {
        $total = $this->jalons()->count();

        return $total > 0 ? (int) round($this->jalons()->whereNotNull('realise_le')->count() / $total * 100) : 0;
    }

    /** Temps écoulé entre le début et la fin prévue. */
    public function tempsEcoule(): int
    {
        $duree = max(1, $this->date_debut->diffInDays($this->date_fin));
        $ecoule = $this->date_debut->diffInDays(now(), false);

        return (int) max(0, min(100, round($ecoule / $duree * 100)));
    }

    /** Les dépenses du projet : celles notées dans son suivi, et les ordres de dépense exécutés pour lui. */
    public function depenses(): int
    {
        return (int) $this->suivis()->where('type', TypeSuivi::Depense->value)->sum('montant')
            + (int) OrdreDepense::query()->where('projet_id', $this->id)->where('statut', OrdreDepense::EXECUTE)->sum('montant');
    }

    public function jalonsEnRetard(): int
    {
        return $this->jalons()->whereNull('realise_le')->whereDate('echeance', '<', now())->count();
    }

    /** Le plus gros retard d'une étape non réalisée, en jours. */
    public function retardMaximal(): int
    {
        $plusAncienne = $this->jalons()->whereNull('realise_le')->whereDate('echeance', '<', now())->min('echeance');

        return $plusAncienne !== null ? (int) Carbon::parse((string) $plusAncienne)->diffInDays(now()) : 0;
    }

    /**
     * Le feu tricolore et sa raison.
     *
     * @return array{0: SanteProjet, 1: string}
     */
    public function diagnostic(): array
    {
        if ($this->statut->estClos()) {
            return [SanteProjet::Clos, 'Projet '.mb_strtolower($this->statut->getLabel()).'.'];
        }

        $avancement = $this->avancement();

        return match (true) {
            $this->budget !== null && $this->depenses() > $this->budget => [SanteProjet::Rouge, 'Les dépenses dépassent le budget.'],
            $this->date_fin->copy()->endOfDay()->isPast() && $avancement < 100 => [SanteProjet::Rouge, 'La date de fin est dépassée.'],
            $this->retardMaximal() > 15 => [SanteProjet::Rouge, 'Une étape a plus de 15 jours de retard.'],
            $this->statut === StatutProjet::Suspendu => [SanteProjet::Orange, 'Le projet est suspendu.'],
            $this->jalonsEnRetard() > 0 => [SanteProjet::Orange, $this->jalonsEnRetard().' étape(s) en retard.'],
            $this->tempsEcoule() - $avancement > 25 => [SanteProjet::Orange, 'L\'avancement est en retard sur le calendrier.'],
            default => [SanteProjet::Vert, 'Le projet avance comme prévu.'],
        };
    }

    public function sante(): SanteProjet
    {
        return $this->diagnostic()[0];
    }

    /** @return BelongsTo<Commission, $this> */
    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    /** @return BelongsTo<Membre, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Membre::class, 'responsable_id');
    }

    /** @return BelongsTo<Collecte, $this> */
    public function collecte(): BelongsTo
    {
        return $this->belongsTo(Collecte::class);
    }

    /** @return HasMany<Jalon, $this> */
    public function jalons(): HasMany
    {
        return $this->hasMany(Jalon::class)->whereNull('archive_le');
    }

    /** @return HasMany<Indicateur, $this> */
    public function indicateurs(): HasMany
    {
        return $this->hasMany(Indicateur::class)->whereNull('archive_le');
    }

    /** @return HasMany<SuiviProjet, $this> */
    public function suivis(): HasMany
    {
        return $this->hasMany(SuiviProjet::class);
    }
}
