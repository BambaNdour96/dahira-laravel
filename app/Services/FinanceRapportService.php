<?php

namespace App\Services;

use App\Models\Caisse;
use App\Models\Collecte;
use App\Models\Exercice;
use App\Models\MouvementCaisse;
use App\Models\OrdreDepense;
use App\Models\ParticipationSympathisant;
use App\Models\Projet;
use App\Models\ReaffectationFonds;
use Dompdf\Dompdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

/**
 * Les rapports financiers, le bilan d'un exercice et les soldes des fonds. Tout se calcule sur le journal des
 * mouvements de caisse, qui ne s'efface jamais : une correction faite plus tard compte à sa date, dans l'exercice où
 * elle a lieu. Le bilan d'un exercice clos est figé (voir ExerciceService).
 */
final class FinanceRapportService
{
    /** Les sources de recettes, par objet de paiement. */
    public const SOURCES = [
        'frais_carte' => 'Frais de carte', 'cotisation' => 'Cotisations', 'renouvellement' => 'Renouvellements',
        'carte_physique' => 'Cartes en plastique', 'collecte' => 'Jàyante et Sass', 'sympathisant' => 'Participations des sympathisants',
        'avoir' => 'Avoirs à imputer',
    ];

    /**
     * Les recettes de la période, une ligne par mouvement (les contre-passations en négatif).
     *
     * @return Collection<int, array{mois: string, source: string, programme: ?string, cellule: ?string, membre: ?string, montant: int}>
     */
    public function lignesRecettes(Carbon $debut, Carbon $fin): Collection
    {
        return MouvementCaisse::query()
            ->with(['paiement.collecte', 'paiement.cellule', 'paiement.membre.cellule', 'participation.collecte', 'participation.sympathisant.cellule', 'avoir.membre.cellule'])
            ->where(fn ($query) => $query->whereIn('nature', ['encaissement', 'correction'])
                ->orWhere(fn ($reimputation) => $reimputation->where('nature', 'reaffectation')->where(fn ($cible) => $cible->whereNotNull('paiement_id')->orWhereNotNull('avoir_id'))))
            ->whereBetween('date_operation', [$debut->copy()->startOfDay(), $fin->copy()->endOfDay()])
            ->orderBy('date_operation')
            ->get()
            ->map(fn (MouvementCaisse $mouvement): array => $this->ligneRecette($mouvement))
            ->values();
    }

    /**
     * Une recette : sa source, son programme, sa cellule et son membre.
     *
     * @return array{mois: string, source: string, programme: ?string, cellule: ?string, membre: ?string, montant: int}
     */
    private function ligneRecette(MouvementCaisse $mouvement): array
    {
        $paiement = $mouvement->paiement;
        $participation = $mouvement->participation;
        $avoir = $mouvement->avoir;
        $signe = $mouvement->sens === MouvementCaisse::ENTREE ? 1 : -1;
        $source = match (true) {
            $participation !== null => self::SOURCES['sympathisant'],
            $avoir !== null => self::SOURCES['avoir'],
            default => self::SOURCES[$paiement->objet ?? ''] ?? 'Autres',
        };

        return [
            'mois' => $mouvement->date_operation->format('Y-m'),
            'source' => $source,
            'programme' => $participation?->collecte->titre ?? $paiement?->collecte?->titre,
            'cellule' => $this->celluleDe($mouvement),
            'membre' => $paiement?->membre->nom_complet ?? $avoir?->membre->nom_complet,
            'montant' => $signe * $mouvement->montant,
        ];
    }

    /**
     * Une dépense : son libellé, son rattachement, son programme ou son projet.
     *
     * @return array{mois: string, libelle: string, rattachement: string, programme: ?string, projet: ?string, montant: int}
     */
    private function ligneDepense(MouvementCaisse $mouvement): array
    {
        $ordre = $mouvement->ordreDepense ?? throw new RuntimeException('Dépense sans ordre.');

        return [
            'mois' => $mouvement->date_operation->format('Y-m'),
            'libelle' => $ordre->libelle->nom,
            'rattachement' => OrdreDepense::RATTACHEMENTS[$ordre->rattachement] ?? $ordre->rattachement,
            'programme' => $ordre->collecte->titre ?? $ordre->commissionComite?->instance->nom,
            'projet' => $ordre->projet?->titre,
            // Le reste d'une avance rendu à la Finance diminue la dépense, le mois où il est rendu.
            'montant' => $mouvement->sens === MouvementCaisse::SORTIE ? $mouvement->montant : -$mouvement->montant,
        ];
    }

    /** La cellule d'une recette : celle du paiement, du membre, du sympathisant ou du titulaire de l'avoir. */
    private function celluleDe(MouvementCaisse $mouvement): ?string
    {
        if ($mouvement->participation !== null) {
            return $mouvement->participation->sympathisant->cellule->nom;
        }

        if ($mouvement->paiement !== null) {
            return ($mouvement->paiement->cellule ?? $mouvement->paiement->membre->cellule)->nom;
        }

        return $mouvement->avoir?->membre->cellule->nom;
    }

    /**
     * Les dépenses de la période : les ordres exécutés (sorties de caisse), moins les restes d'avance rendus.
     *
     * @return Collection<int, array{mois: string, libelle: string, rattachement: string, programme: ?string, projet: ?string, montant: int}>
     */
    public function lignesDepenses(Carbon $debut, Carbon $fin): Collection
    {
        return MouvementCaisse::query()
            ->with(['ordreDepense.libelle', 'ordreDepense.collecte', 'ordreDepense.projet', 'ordreDepense.commissionComite.instance'])
            ->whereIn('nature', ['depense', 'retour'])
            ->whereNotNull('ordre_depense_id')
            ->whereBetween('date_operation', [$debut->copy()->startOfDay(), $fin->copy()->endOfDay()])
            ->orderBy('date_operation')
            ->get()
            ->map(fn (MouvementCaisse $mouvement): array => $this->ligneDepense($mouvement))
            ->values();
    }

    /**
     * Le rapport d'une période : totaux, regroupements, soldes des caisses à la fin, réaffectations.
     *
     * @return array<string, mixed>
     */
    public function rapport(Carbon $debut, Carbon $fin): array
    {
        $recettes = $this->lignesRecettes($debut, $fin);
        $depenses = $this->lignesDepenses($debut, $fin);
        $grouper = fn (Collection $lignes, string $cle): array => $lignes->groupBy(fn (array $ligne): string => (string) ($ligne[$cle] ?? '—'))
            ->map(fn (Collection $groupe): int => (int) $groupe->sum('montant'))->sortKeys()->all();

        return [
            'debut' => $debut->toDateString(),
            'fin' => $fin->toDateString(),
            'recettes' => (int) $recettes->sum('montant'),
            'depenses' => (int) $depenses->sum('montant'),
            'recettes_par_mois' => $grouper($recettes, 'mois'),
            'recettes_par_source' => $grouper($recettes, 'source'),
            'recettes_par_programme' => $grouper($recettes->whereNotNull('programme'), 'programme'),
            'recettes_par_cellule' => $grouper($recettes, 'cellule'),
            'depenses_par_mois' => $grouper($depenses, 'mois'),
            'depenses_par_libelle' => $grouper($depenses, 'libelle'),
            'depenses_par_rattachement' => $grouper($depenses, 'rattachement'),
            'depenses_par_projet' => $grouper($depenses->whereNotNull('projet'), 'projet'),
            'caisses' => $this->soldesAu($fin),
            'reaffectations' => ReaffectationFonds::query()->with(['origineCollecte', 'origineProjet', 'destinationCollecte', 'destinationProjet'])
                ->where('statut', ReaffectationFonds::ENREGISTREE)->whereBetween('enregistree_le', [$debut->copy()->startOfDay(), $fin->copy()->endOfDay()])->get()
                ->map(fn (ReaffectationFonds $r): array => ['numero' => $r->numero, 'origine' => $r->libelleOrigine(), 'destination' => $r->libelleDestination(), 'montant' => $r->montant, 'motif' => $r->motif])
                ->values()->all(),
        ];
    }

    /**
     * Le détail par membre (réservé au Jewrine finance) : ce que chacun a versé, par source.
     *
     * @return array<string, array<string, int>>
     */
    public function detailParMembre(Carbon $debut, Carbon $fin): array
    {
        return $this->lignesRecettes($debut, $fin)->whereNotNull('membre')
            ->groupBy('membre')
            ->map(fn (Collection $lignes): array => $lignes->groupBy('source')->map(fn (Collection $groupe): int => (int) $groupe->sum('montant'))->all())
            ->sortKeys()
            ->all();
    }

    /**
     * Le solde de chaque caisse à une date.
     *
     * @return array<string, int>
     */
    public function soldesAu(Carbon $fin): array
    {
        $resultat = [];

        foreach (Caisse::query()->orderBy('ordre')->orderBy('nom')->get() as $caisse) {
            $mouvements = $caisse->mouvements()->where('date_operation', '<=', $fin->copy()->endOfDay());
            $solde = (int) (clone $mouvements)->where('sens', MouvementCaisse::ENTREE)->sum('montant') - (int) (clone $mouvements)->where('sens', MouvementCaisse::SORTIE)->sum('montant');

            if ($solde !== 0 || ! $caisse->estArchive()) {
                $resultat[$caisse->nom] = $solde;
            }
        }

        return $resultat;
    }

    /** Ce qui reste à un programme : ses recettes, moins ses dépenses, moins ou plus ses réaffectations. */
    public function soldeProgramme(Collecte $collecte): int
    {
        $recettes = (int) MouvementCaisse::query()->whereIn('nature', ['encaissement', 'correction', 'reaffectation'])
            ->where(fn ($query) => $query->whereHas('paiement', fn ($paiement) => $paiement->where('collecte_id', $collecte->id))
                ->orWhereIn('participation_id', ParticipationSympathisant::query()->where('collecte_id', $collecte->id)->select('id')))
            ->get()->sum(fn (MouvementCaisse $mouvement): int => $mouvement->sens === MouvementCaisse::ENTREE ? $mouvement->montant : -$mouvement->montant);
        $depenses = OrdreDepense::decaisseNet(OrdreDepense::query()->where('collecte_id', $collecte->id));

        return $recettes - $depenses + $this->reaffecte('collecte', $collecte->id);
    }

    /** Ce qui reste à un projet : ses Jàyante (s'il a sa collecte) et ses réaffectations reçues, moins ses dépenses. */
    public function soldeProjet(Projet $projet): int
    {
        $collecte = $projet->collecte_id !== null ? Collecte::query()->whereKey($projet->collecte_id)->first() : null;
        $depenses = OrdreDepense::decaisseNet(OrdreDepense::query()->where('projet_id', $projet->id));

        return ($collecte !== null ? $this->soldeProgramme($collecte) : 0) - $depenses + $this->reaffecte('projet', $projet->id);
    }

    /**
     * Ce qui reste au fonctionnement : la trésorerie de toutes les caisses, moins ce qui revient encore aux programmes et
     * aux projets, plus ou moins les réaffectations du fonctionnement.
     */
    public function soldeFonctionnement(): int
    {
        $tresorerie = (int) Caisse::query()->get()->sum(fn (Caisse $caisse): int => $caisse->solde());
        $collectesDeProjets = Projet::query()->whereNotNull('collecte_id')->pluck('collecte_id')->all();
        $reserve = Collecte::query()->whereNotIn('id', $collectesDeProjets)->get()->sum(fn (Collecte $collecte): int => max(0, $this->soldeProgramme($collecte)))
            + Projet::query()->get()->sum(fn (Projet $projet): int => max(0, $this->soldeProjet($projet)));

        return $tresorerie - (int) $reserve;
    }

    /** Le solde d'un fonds : un programme, un projet ou le fonctionnement. */
    public function soldeFonds(string $type, ?int $id): int
    {
        return match ($type) {
            'programme' => $this->soldeProgramme(Collecte::query()->whereKey($id ?? 0)->firstOrFail()),
            'projet' => $this->soldeProjet(Projet::query()->whereKey($id ?? 0)->firstOrFail()),
            default => $this->soldeFonctionnement(),
        };
    }

    /** Les réaffectations enregistrées reçues moins celles données, pour un programme ou un projet. */
    private function reaffecte(string $type, int $id): int
    {
        $enregistrees = ReaffectationFonds::query()->where('statut', ReaffectationFonds::ENREGISTREE);

        return (int) (clone $enregistrees)->where('destination_'.$type.'_id', $id)->sum('montant')
            - (int) (clone $enregistrees)->where('origine_'.$type.'_id', $id)->sum('montant');
    }

    /**
     * Le bilan d'un exercice : figé s'il est clos, calculé sinon.
     *
     * @return array<string, mixed>
     */
    public function bilan(Exercice $exercice): array
    {
        return $exercice->estClos() && is_array($exercice->bilan) ? $exercice->bilan : $this->rapport($exercice->date_debut, $exercice->date_fin);
    }

    /** Le bilan en PDF, pour l'assemblée générale. */
    public function pdfBilan(Exercice $exercice): string
    {
        $logo = public_path('images/logo-ndk.png');
        $html = view('pdf.bilan', [
            'exercice' => $exercice,
            'bilan' => $this->bilan($exercice),
            'logo' => is_file($logo) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logo)) : null,
        ])->render();

        $pdf = new Dompdf(['defaultFont' => 'DejaVu Sans']);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return (string) $pdf->output();
    }

    /**
     * Le rapport en Excel : une feuille par tableau ; le détail par membre si on y a droit.
     *
     * @param  array<string, mixed>  $rapport
     * @param  array<string, array<string, int>>|null  $parMembre
     */
    public function excel(array $rapport, ?array $parMembre, string $chemin): void
    {
        $writer = new Writer;
        $writer->openToFile($chemin);
        $premiere = true;

        $feuilles = [
            'Résumé' => ['Recettes' => $rapport['recettes'], 'Dépenses' => $rapport['depenses'], 'Solde de la période' => $rapport['recettes'] - $rapport['depenses']],
            'Recettes par mois' => $rapport['recettes_par_mois'], 'Recettes par source' => $rapport['recettes_par_source'],
            'Recettes par programme' => $rapport['recettes_par_programme'], 'Recettes par cellule' => $rapport['recettes_par_cellule'],
            'Dépenses par mois' => $rapport['depenses_par_mois'], 'Dépenses par libellé' => $rapport['depenses_par_libelle'],
            'Dépenses par rattachement' => $rapport['depenses_par_rattachement'], 'Dépenses par projet' => $rapport['depenses_par_projet'],
            'Caisses' => $rapport['caisses'],
        ];

        foreach ($feuilles as $nom => $lignes) {
            $feuille = $premiere ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $feuille->setName(mb_substr($nom, 0, 31));
            $premiere = false;
            $writer->addRow(Row::fromValues(['Période : du '.$rapport['debut'].' au '.$rapport['fin']]));
            $writer->addRow(Row::fromValues(['', 'FCFA']));

            foreach ((array) $lignes as $cle => $montant) {
                $writer->addRow(Row::fromValues([(string) $cle, (int) $montant]));
            }
        }

        if ($parMembre !== null) {
            $writer->addNewSheetAndMakeItCurrent()->setName('Détail par membre');
            $writer->addRow(Row::fromValues(['Membre', ...array_values(self::SOURCES)]));

            foreach ($parMembre as $membre => $sources) {
                $writer->addRow(Row::fromValues([$membre, ...array_map(fn (string $source): int => $sources[$source] ?? 0, array_values(self::SOURCES))]));
            }
        }

        $writer->close();
    }
}
