<?php

namespace App\Services;

use App\Enums\ModeCollecte;
use App\Enums\StatutCollecte;
use App\Models\ActionComite;
use App\Models\AppartenanceCommission;
use App\Models\Collecte;
use App\Models\Commission;
use App\Models\Instance;
use App\Models\Membre;
use App\Models\Nomination;
use App\Models\OrdreDepense;
use App\Models\Poste;
use App\Models\ProgrammeCommission;
use App\Models\ReaffectationFonds;
use App\Models\TypeProgramme;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Observers\AvisMembreObserver;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Les événements de la Daara (Magal, Ziar, Prokhane, Gamou…), créés et réglés d'un seul formulaire par le super
 * administrateur ou le Jewrine national : dates et lieu, jour J, contribution (le super administrateur choisit Jàyante
 * ou Sass, le Jewrine national fixe le Sass), objectif et budget, façon d'organiser, itinéraire et transport. Celui qui
 * pilote l'événement confie les tâches aux commissions choisies, que leur Jewrine répartit. À la clôture, le bilan ; le
 * solde rejoint la caisse générale par une réaffectation décidée par le Jewrine national, enregistrée par la Finance.
 */
final class ProgrammeService
{
    /** Les champs du formulaire qui ne sont pas des colonnes de l'événement. */
    private const HORS_COLONNES = ['avec_jour_j', 'president_id', 'commissions'];

    public function __construct(
        private readonly CollecteService $collectes,
        private readonly JourJService $joursJ,
        private readonly FinanceRapportService $rapports,
    ) {}

    /**
     * Crée ou met à jour un événement, avec son jour J, son organisation et ses engagements au Sass.
     *
     * @param  array<string, mixed>  $donnees
     */
    public function enregistrer(array $donnees, ?Collecte $existant, User $auteur): Collecte
    {
        if (! $auteur->can('programmes.parametrer')) {
            throw new DomainException('Seuls le super administrateur et le Jewrine national créent et règlent un événement.');
        }

        $avecJourJ = (bool) ($donnees['avec_jour_j'] ?? false);
        $presidentId = filled($donnees['president_id'] ?? null) ? (int) $donnees['president_id'] : null;
        $commissions = $this->commissionsChoisies($donnees['commissions'] ?? []);
        $valeurs = array_diff_key($donnees, array_flip(self::HORS_COLONNES));
        $valeurs = $this->verifier($valeurs, $commissions, $existant, $auteur);
        $ancienSass = $existant !== null && $existant->estSass() ? $existant->montant : null;

        $collecte = DB::transaction(function () use ($valeurs, $existant, $auteur, $avecJourJ, $presidentId, $commissions): Collecte {
            $collecte = $existant ?? new Collecte(['cree_par' => $auteur->id]);
            $collecte->fill($valeurs)->save();

            $this->joursJ->synchroniser($collecte, $avecJourJ, $auteur->id);
            $collecte->organisation === Collecte::COMITE
                ? $this->comiteDedie($collecte, $presidentId, $auteur)
                : $this->synchroniserCommissions($collecte, $commissions);

            return $collecte;
        });

        $this->collectes->engagerAuSass($collecte, $auteur);
        $this->prevenirCeQuiReste($collecte, $auteur);

        if ($ancienSass !== null && $collecte->estSass() && $collecte->montant !== null && $collecte->montant !== $ancienSass) {
            $this->prevenirChangementDeSass($collecte, $ancienSass);
        }

        return $collecte->refresh();
    }

    /** Le Sass a changé : chaque membre engagé est prévenu, avec l'ancien et le nouveau montant, et son engagement. */
    private function prevenirChangementDeSass(Collecte $collecte, int $ancien): void
    {
        foreach ($collecte->engagements()->with('membre.user', 'membre.responsable.user')->get() as $engagement) {
            AvisMembreObserver::avertir(
                $engagement->membre,
                'Le Sass change : '.$collecte->titre,
                'Le Sass de « '.$collecte->titre.' » passe de '.CollecteService::fcfa($ancien).' à '.CollecteService::fcfa((int) $collecte->montant)
                    .'. Votre engagement : '.CollecteService::fcfa($engagement->montant).'.',
                route('engagements.index'),
                'Mes Sass et Jàyante',
            );
        }
    }

    /**
     * Les règles : type en activité, dates et lieu, contribution choisie par le super administrateur seulement, Sass
     * fixé, budget des commissions dans l'enveloppe.
     *
     * @param  array<string, mixed>  $valeurs
     * @param  array<int, ?int>  $commissions
     * @return array<string, mixed>
     */
    private function verifier(array $valeurs, array $commissions, ?Collecte $existant, User $auteur): array
    {
        $typeId = (int) ($valeurs['type_programme_id'] ?? 0);
        $type = TypeProgramme::query()->whereKey($typeId)->first();

        if ($type === null || ($type->estArchive() && $existant?->type_programme_id !== $typeId)) {
            throw new DomainException('Choisissez le type d\'événement, parmi ceux en activité.');
        }

        if (trim((string) ($valeurs['titre'] ?? '')) === '' || blank($valeurs['debut'] ?? null) || trim((string) ($valeurs['lieu'] ?? '')) === '') {
            throw new DomainException('Indiquez le nom, la date et le lieu de l\'événement.');
        }

        if (filled($valeurs['fin'] ?? null) && Carbon::parse($valeurs['fin'])->lt(Carbon::parse($valeurs['debut']))) {
            throw new DomainException('La fin de l\'événement vient après son début.');
        }

        if (! in_array($valeurs['organisation'] ?? null, [Collecte::COMITE, Collecte::COMMISSIONS], true)) {
            throw new DomainException('Choisissez la façon d\'organiser : un comité dédié, ou des commissions permanentes.');
        }

        // Le mode de contribution : le super administrateur seul le choisit, et il ne change plus une fois des engagements pris.
        $mode = $valeurs['mode'] ?? null;
        $mode = $mode instanceof ModeCollecte ? $mode : ModeCollecte::tryFrom((string) $mode);

        if (! $auteur->can('programmes.contribution')) {
            $mode = $existant?->mode;
        } elseif ($existant !== null && $existant->mode !== null && $mode !== $existant->mode && $existant->engagements()->where('fixe_par', '!=', 'auto')->exists()) {
            throw new DomainException('Des membres sont déjà engagés : le mode de contribution ne change plus.');
        }

        $valeurs['mode'] = $mode;
        $valeurs['montant'] = $mode === ModeCollecte::Sass && filled($valeurs['montant'] ?? null) ? (int) $valeurs['montant'] : null;

        if ($valeurs['montant'] !== null && $valeurs['montant'] < 1) {
            throw new DomainException('Le Sass est un montant positif.');
        }

        $budget = filled($valeurs['budget'] ?? null) ? (int) $valeurs['budget'] : null;
        $valeurs['budget'] = $budget;
        $valeurs['objectif'] = filled($valeurs['objectif'] ?? null) ? (int) $valeurs['objectif'] : null;

        if ($valeurs['organisation'] === Collecte::COMMISSIONS && $budget !== null && array_sum(array_map('intval', $commissions)) > $budget) {
            throw new DomainException('Les parts des commissions dépassent le budget de l\'événement ('.CollecteService::fcfa($budget).').');
        }

        $valeurs['itineraire_transport'] = (bool) ($valeurs['itineraire_transport'] ?? false);

        return $valeurs;
    }

    /**
     * Les commissions cochées, avec leur part du budget : commission => budget.
     *
     * @return array<int, ?int>
     */
    private function commissionsChoisies(mixed $lignes): array
    {
        $choisies = [];

        foreach (is_array($lignes) ? $lignes : [] as $ligne) {
            if (is_array($ligne) && filled($ligne['commission_id'] ?? null)) {
                $choisies[(int) $ligne['commission_id']] = filled($ligne['budget'] ?? null) ? (int) $ligne['budget'] : null;
            }
        }

        return $choisies;
    }

    /** Le comité d'organisation de l'événement, créé à la première fois ; son président est désigné ici. */
    private function comiteDedie(Collecte $collecte, ?int $presidentId, User $auteur): void
    {
        $comite = $collecte->comite;
        $president = $presidentId !== null ? Membre::query()->whereKey($presidentId)->first() : null;

        if ($comite === null) {
            $estMagal = $collecte->type?->code === TypeProgramme::MAGAL;
            $comite = Instance::query()->create([
                'type' => Instance::COMITE_ORGANISATION,
                'nom' => ($estMagal ? 'CDGMT – ' : 'Comité d\'organisation – ').$collecte->titre,
                // Le comité de suivi des dépenses : d'office pour un Magal ; le Jewrine national peut l'activer ailleurs.
                'suivi_depenses' => $estMagal,
                'code' => $estMagal ? 'CDGMT' : 'COM',
                'annee' => ($collecte->debut ?? now())->year,
                'collecte_id' => $collecte->id,
                'president_id' => $president?->id,
                'cree_par' => $auteur->id,
            ]);
        } elseif ($president !== null && $comite->president_id !== $president->id) {
            $comite->update(['president_id' => $president->id]);
        } else {
            return;
        }

        app(ComiteService::class)->preparerCdgmt($comite);
        app(ComiteService::class)->integrerResponsables($comite);

        if ($president?->user !== null && $president->user_id !== $auteur->id) {
            $president->user->notify(new NotificationMembre(
                'Président du comité : '.$collecte->titre,
                'Vous êtes désigné président du comité d\'organisation de « '.$collecte->titre.' ».',
                route('comites.index'),
                'Mes comités',
            ));
        }
    }

    /**
     * Les commissions choisies : ajoutées, budget mis à jour, ou retirées (jamais effacées).
     *
     * @param  array<int, ?int>  $commissions
     */
    private function synchroniserCommissions(Collecte $collecte, array $commissions): void
    {
        foreach ($collecte->commissionsChoisies()->get() as $choix) {
            if (! array_key_exists($choix->commission_id, $commissions) && $choix->retiree_le === null) {
                $choix->update(['retiree_le' => now()]);
            }
        }

        foreach ($commissions as $commissionId => $budget) {
            if (! Commission::query()->whereKey($commissionId)->exists()) {
                continue;
            }

            $choix = ProgrammeCommission::query()->firstOrNew(['collecte_id' => $collecte->id, 'commission_id' => $commissionId]);
            $nouvelle = ! $choix->exists || $choix->retiree_le !== null;
            $choix->fill(['budget' => $budget, 'retiree_le' => null])->save();

            if ($nouvelle) {
                $choix->commission->jewrine()?->notify(new NotificationMembre(
                    'Votre commission organise : '.$collecte->titre,
                    'La commission '.$choix->commission->nom.' est choisie pour organiser « '.$collecte->titre.' »'.($budget !== null ? ', avec un budget de '.CollecteService::fcfa($budget) : '').'.',
                    route('comites.index'),
                    'Mes comités',
                ));
            }
        }
    }

    /** Le super administrateur choisit la contribution ; le Jewrine national fixe le Sass. */
    private function prevenirCeQuiReste(Collecte $collecte, User $auteur): void
    {
        if ($collecte->mode === null) {
            $this->prevenir(User::permission('programmes.contribution')->get(), $auteur, 'Contribution à choisir : '.$collecte->titre,
                'Choisissez le mode de contribution (Jàyante ou Sass) de « '.$collecte->titre.' » : la collecte s\'ouvrira ensuite aux membres.', $collecte);
        } elseif ($collecte->estSass() && ! $collecte->contributionReglee()) {
            $this->prevenir(User::query()->whereIn('id', Nomination::query()->whereHas('poste', fn ($query) => $query->where('code', Poste::JEWRINE_NATIONAL))->select('user_id'))->get(), $auteur,
                'Sass à fixer : '.$collecte->titre, 'Fixez le montant du Sass de « '.$collecte->titre.' » : les membres y seront engagés d\'office.', $collecte);
        }
    }

    /** @param  iterable<User>  $destinataires */
    private function prevenir(iterable $destinataires, User $auteur, string $titre, string $message, Collecte $collecte): void
    {
        foreach ($destinataires as $destinataire) {
            if ($destinataire->id !== $auteur->id) {
                $destinataire->notify(new NotificationMembre($titre, $message, route('filament.admin.resources.collectes.edit', $collecte), 'Régler l\'événement'));
            }
        }
    }

    /** Celui qui pilote l'événement (super administrateur, Jewrine national) confie une tâche à une commission choisie. */
    public function confierTache(Collecte $collecte, Commission $commission, string $description, ?string $echeance, User $auteur): ActionComite
    {
        if (! $auteur->can('programmes.parametrer')) {
            throw new DomainException('Celui qui pilote l\'événement confie ses tâches aux commissions.');
        }

        if (! $collecte->commissionsChoisies()->where('commission_id', $commission->id)->whereNull('retiree_le')->exists()) {
            throw new DomainException('La commission '.$commission->nom.' n\'est pas choisie pour cet événement.');
        }

        if (trim($description) === '') {
            throw new DomainException('Décrivez la tâche.');
        }

        $espace = app(CommissionService::class)->comite($commission);

        $tache = ActionComite::query()->create([
            'instance_id' => $espace->id, 'collecte_id' => $collecte->id, 'description' => mb_substr(trim($description), 0, 255), 'echeance' => $echeance,
        ]);

        $commission->jewrine()?->notify(new NotificationMembre(
            'Tâche pour votre commission : '.$collecte->titre,
            trim($description).($echeance !== null ? ' — pour le '.Carbon::parse($echeance)->format('d/m/Y') : '').'. Répartissez-la entre vos membres.',
            route('comites.index'),
            'Répartir la tâche',
        ));

        return $tache;
    }

    /** Le Jewrine de la commission répartit une tâche de l'événement entre ses membres. */
    public function repartirTache(ActionComite $tache, Membre $membre, User $auteur): void
    {
        $commission = $tache->instance->commission;

        if ($tache->collecte_id === null || $commission === null) {
            throw new DomainException('Cette tâche ne vient pas d\'un événement.');
        }

        $dirige = Commission::query()->dirigeesPar($auteur)->whereKey($commission->id)->exists();

        if (! $dirige && ! $auteur->can('programmes.parametrer')) {
            throw new DomainException('Le Jewrine de la commission répartit ses tâches.');
        }

        $membreDeLaCommission = AppartenanceCommission::query()->actives()->where('commission_id', $commission->id)->where('membre_id', $membre->id)->exists()
            || $commission->jewrine()?->id === $membre->user_id;

        if (! $membreDeLaCommission) {
            throw new DomainException('La tâche se confie à un membre de la commission '.$commission->nom.'.');
        }

        $tache->update(['responsable_id' => $membre->id]);

        if ($membre->user !== null && $membre->user_id !== $auteur->id) {
            $membre->user->notify(new NotificationMembre(
                'Nouvelle tâche : '.($tache->collecte->titre ?? 'événement'),
                $tache->description.($tache->echeance !== null ? ' — pour le '.$tache->echeance->format('d/m/Y') : '').'.',
                route('comites.index'),
                'Voir mes tâches',
            ));
        }
    }

    /**
     * Le bilan : objectif atteint ou non, dépenses face au budget (et par commission), solde.
     *
     * @return array{objectif: ?int, encaisse: int, atteint: ?bool, taux: ?int, budget: ?int, depense: int, depasse: ?bool, solde: int, commissions: list<array{nom: string, budget: ?int, depense: int}>, solde_reaffecte: ?string}
     */
    public function bilan(Collecte $collecte): array
    {
        $encaisse = $collecte->totalVerse();
        $ordres = OrdreDepense::query()->with(['commission', 'commissionComite'])->where('collecte_id', $collecte->id)->where('statut', OrdreDepense::EXECUTE)->get();
        $depense = (int) $ordres->sum(fn (OrdreDepense $ordre): int => $ordre->decaisse());
        $commissions = [];

        foreach ($collecte->commissionsChoisies()->with('commission')->whereNull('retiree_le')->get() as $choix) {
            $commissions[$choix->commission->nom] = ['nom' => $choix->commission->nom, 'budget' => $choix->budget, 'depense' => 0];
        }

        foreach ($ordres as $ordre) {
            $nom = $ordre->commission->nom ?? $ordre->commissionComite?->nom;

            if ($nom !== null) {
                $commissions[$nom] ??= ['nom' => $nom, 'budget' => null, 'depense' => 0];
                $commissions[$nom]['depense'] += $ordre->decaisse();
            }
        }

        $reaffectation = ReaffectationFonds::query()->where('origine_collecte_id', $collecte->id)->where('destination', 'fonctionnement')
            ->where('statut', '!=', ReaffectationFonds::ANNULEE)->latest()->first();

        return [
            'objectif' => $collecte->objectif,
            'encaisse' => $encaisse,
            'atteint' => $collecte->objectif !== null ? $encaisse >= $collecte->objectif : null,
            'taux' => $collecte->objectif !== null && $collecte->objectif > 0 ? (int) round($encaisse / $collecte->objectif * 100) : null,
            'budget' => $collecte->budget,
            'depense' => $depense,
            'depasse' => $collecte->budget !== null ? $depense > $collecte->budget : null,
            'solde' => $this->rapports->soldeProgramme($collecte),
            'commissions' => array_values($commissions),
            'solde_reaffecte' => $reaffectation !== null ? $reaffectation->numero.' · '.(ReaffectationFonds::LIBELLES[$reaffectation->statut] ?? $reaffectation->statut) : null,
        ];
    }

    /** Clôturer : les versements s'arrêtent ; le Jewrine national fait rejoindre le solde à la caisse générale. */
    public function cloturer(Collecte $collecte, User $auteur): ?ReaffectationFonds
    {
        if (! $auteur->can('programmes.cloturer')) {
            throw new DomainException('Le Jewrine national clôture un événement.');
        }

        $collecte->update(['statut' => StatutCollecte::Cloturee]);

        if ($this->peutVerserLeSolde($auteur, $collecte)) {
            return $this->verserLeSolde($collecte, $auteur);
        }

        $solde = $this->rapports->soldeProgramme($collecte);

        if ($solde > 0) {
            foreach (User::permission('reaffectations.decider')->get() as $national) {
                if ($national->id !== $auteur->id && ! $national->hasRole('super_administrateur')) {
                    $national->notify(new NotificationMembre('Solde à verser : '.$collecte->titre, '« '.$collecte->titre.' » est clos avec un solde de '.CollecteService::fcfa($solde).' : décidez sa réaffectation vers la caisse générale.', route('filament.admin.resources.collectes.view', $collecte), 'Voir le bilan'));
                }
            }
        }

        return null;
    }

    public function peutVerserLeSolde(User $utilisateur, Collecte $collecte): bool
    {
        return $collecte->statut === StatutCollecte::Cloturee
            && $utilisateur->can('reaffectations.decider')
            && $this->rapports->soldeProgramme($collecte) > 0
            && ! ReaffectationFonds::query()->where('origine_collecte_id', $collecte->id)->where('statut', ReaffectationFonds::DECIDEE)->exists();
    }

    /** La réaffectation du solde vers le fonctionnement (la caisse générale), décidée ici, enregistrée par la Finance. */
    public function verserLeSolde(Collecte $collecte, User $auteur): ReaffectationFonds
    {
        if (! $this->peutVerserLeSolde($auteur, $collecte)) {
            throw new DomainException('Le Jewrine national verse le solde d\'un événement clos, une fois.');
        }

        return app(ReaffectationService::class)->decider([
            'origine' => 'programme', 'origine_collecte_id' => $collecte->id, 'destination' => 'fonctionnement',
            'montant' => $this->rapports->soldeProgramme($collecte), 'motif' => 'Solde de « '.$collecte->titre.' » à la clôture : vers la caisse générale',
        ], $auteur);
    }
}
