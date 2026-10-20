<?php

namespace App\Http\Controllers;

use App\Models\ActionComite;
use App\Models\Commission;
use App\Models\CommissionComite;
use App\Models\Competence;
use App\Models\Instance;
use App\Models\LibelleDepense;
use App\Models\Membre;
use App\Models\OrdreDepense;
use App\Models\User;
use App\Models\Volontariat;
use App\Services\ArabeCoranService;
use App\Services\ComiteService;
use App\Services\CompetencesService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** « Mes comités » : les comités du membre, leurs réunions, leurs PV publiés et ses actions. */
class ComitesController extends Controller
{
    public function index(Request $request): View
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 403);
        $membre = $utilisateur->membre;
        abort_unless($membre !== null, 403);

        $instances = Instance::query()
            ->whereHas('membres', fn ($query) => $query->whereKey($membre->id))
            ->where('statut', 'active')
            ->with(['reunions' => fn ($query) => $query->with('points')->latest('debut')->limit(6), 'commissions.chef'])
            ->get();

        $actions = ActionComite::query()
            ->with(['instance', 'commission'])
            ->where(function ($query) use ($membre): void {
                $query->where('responsable_id', $membre->id)
                    ->orWhereIn('commission_id', CommissionComite::query()->where('chef_id', $membre->id)->select('id'));
            })
            ->whereNotIn('statut', ActionComite::TERMINEES)
            ->orderBy('echeance')
            ->get();

        $mesCommissions = CommissionComite::query()
            ->with([
                'instance', 'membres', 'actions.responsable', 'besoins' => fn ($query) => $query->latest(), 'depenses' => fn ($query) => $query->latest('date_depense'),
                'volontariats' => fn ($query) => $query->whereIn('statut', Volontariat::EN_ATTENTE)->with('membre.cellule')->oldest(),
            ])
            ->where('chef_id', $membre->id)
            ->whereHas('instance', fn ($query) => $query->where('statut', 'active'))
            ->get();

        // Les commissions permanentes que je dirige : leurs membres et les souhaits à traiter.
        $commissionsDirigees = Commission::query()
            ->dirigeesPar($utilisateur)
            ->with([
                'appartenances' => fn ($query) => $query->whereNull('refuse_le')->whereNull('fin_le')->with('membre')->orderBy('created_at'),
            ])
            ->orderBy('nom')
            ->get();

        // Le chef cherche des membres par savoir-faire, dans toute la Daara (nom et cellule seulement).
        $competence = $mesCommissions->isNotEmpty() && $request->filled('competence')
            ? Competence::query()->enActivite()->whereKey($request->integer('competence'))->first()
            : null;

        return view('espace.comites', [
            'competences' => $mesCommissions->isNotEmpty() ? CompetencesService::liste() : [],
            'libellesDepense' => $mesCommissions->isNotEmpty() ? LibelleDepense::query()->enActivite()->orderBy('ordre')->pluck('nom', 'id')->all() : [],
            'ordres' => OrdreDepense::query()->with('libelle')->whereIn('commission_comite_id', $mesCommissions->modelKeys())->latest()->get()->groupBy('commission_comite_id'),
            'competence' => $competence,
            'trouves' => $competence !== null ? app(CompetencesService::class)->rechercher($competence) : collect(),
            'recensementArabeCoran' => app(ArabeCoranService::class)->peutVoirRecensement($utilisateur),
            'instances' => $instances,
            'actions' => $actions,
            'mesCommissions' => $mesCommissions,
            'commissionsDirigees' => $commissionsDirigees,
        ]);
    }

    public function statut(Request $request, ActionComite $action, ComiteService $comites): RedirectResponse
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 403);
        $statut = (string) $request->validate(['statut' => ['required', 'in:a_faire,en_cours,faite']])['statut'];

        try {
            $comites->changerStatut($action, $statut, $utilisateur);
        } catch (DomainException $e) {
            return back()->with('comite_erreur', $e->getMessage());
        }

        return back()->with('comite_succes', '« '.$action->description.' » : '.mb_strtolower(ActionComite::STATUTS[$statut]).'.');
    }

    public function ajouterMembre(Request $request, CommissionComite $commission, ComiteService $comites): RedirectResponse
    {
        $saisie = (string) $request->validate(['membre' => ['required', 'string', 'max:30']])['membre'];
        $membre = ComiteService::trouverMembre($saisie);

        if ($membre === null) {
            return back()->with('comite_erreur', 'Aucun membre validé avec ce matricule ou ce numéro.');
        }

        try {
            $comites->ajouterMembreCommission($commission, $membre, $this->utilisateur($request));
        } catch (DomainException $e) {
            return back()->with('comite_erreur', $e->getMessage());
        }

        return back()->with('comite_succes', $membre->prenom.' '.$membre->nom.' rejoint la commission '.$commission->nom.'.');
    }

    public function retirerMembre(Request $request, CommissionComite $commission, Membre $membre, ComiteService $comites): RedirectResponse
    {
        try {
            $comites->retirerMembreCommission($commission, $membre, $this->utilisateur($request));
        } catch (DomainException $e) {
            return back()->with('comite_erreur', $e->getMessage());
        }

        return back()->with('comite_succes', $membre->prenom.' '.$membre->nom.' ne fait plus partie de la commission.');
    }

    public function tache(Request $request, CommissionComite $commission, ComiteService $comites): RedirectResponse
    {
        $donnees = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'membre_id' => ['required', 'integer'],
            'echeance' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        $membre = Membre::query()->find((int) $donnees['membre_id']);

        if ($membre === null) {
            return back()->with('comite_erreur', 'Membre introuvable.');
        }

        try {
            $comites->confierTache($commission, (string) $donnees['description'], $membre, isset($donnees['echeance']) ? (string) $donnees['echeance'] : null, $this->utilisateur($request));
        } catch (DomainException $e) {
            return back()->with('comite_erreur', $e->getMessage());
        }

        return back()->with('comite_succes', 'Tâche confiée à '.$membre->prenom.' '.$membre->nom.' : il est prévenu.');
    }

    private function utilisateur(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 403);

        return $utilisateur;
    }
}
