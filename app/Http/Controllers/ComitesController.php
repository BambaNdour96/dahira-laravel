<?php

namespace App\Http\Controllers;

use App\Models\ActionComite;
use App\Models\CommissionComite;
use App\Models\Instance;
use App\Models\User;
use App\Services\ComiteService;
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
            ->where('statut', '!=', 'faite')
            ->orderBy('echeance')
            ->get();

        return view('espace.comites', ['instances' => $instances, 'actions' => $actions]);
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
}
