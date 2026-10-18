<?php

namespace App\Http\Controllers;

use App\Enums\StatutMembre;
use App\Models\CommissionComite;
use App\Models\Membre;
use App\Models\User;
use App\Models\Volontariat;
use App\Services\VolontariatService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Le volontariat sur le téléphone : se porter volontaire, répondre aux invitations ; et, pour le chef de commission,
 * répondre aux volontaires et inviter des membres trouvés par leurs savoir-faire.
 */
class VolontariatController extends Controller
{
    public function index(Request $request, VolontariatService $volontariat): View
    {
        $membre = self::membre($request);
        $miens = Volontariat::query()->with('commission.instance')->where('membre_id', $membre->id)->latest()->get();

        return view('espace.volontariat', [
            'membre' => $membre,
            'invitations' => $miens->where('statut', Volontariat::INVITE),
            'propositions' => $miens->where('statut', '!=', Volontariat::INVITE),
            'commissions' => $volontariat->commissionsOuvertes()->groupBy(fn (CommissionComite $commission): string => $commission->instance->nom),
            'enAttente' => $miens->filter(fn (Volontariat $v): bool => $v->estEnAttente())->pluck('commission_comite_id')->all(),
            'dejaMembre' => $membre->commissionsComite()->pluck('commissions_comite.id')->all(),
        ]);
    }

    public function proposer(Request $request, CommissionComite $commission, VolontariatService $volontariat): RedirectResponse
    {
        $message = $request->validate(['message' => ['nullable', 'string', 'max:255']])['message'] ?? null;

        try {
            $volontariat->proposer($commission, self::membre($request), is_string($message) ? $message : null, self::utilisateur($request));
        } catch (DomainException $e) {
            return back()->with('volontariat_erreur', $e->getMessage());
        }

        return back()->with('succes', 'Merci ! Votre proposition est envoyée au chef de la commission '.$commission->nom.'.');
    }

    public function retirer(Request $request, Volontariat $volontaire, VolontariatService $volontariat): RedirectResponse
    {
        try {
            $volontariat->retirer($volontaire, self::utilisateur($request));
        } catch (DomainException $e) {
            return back()->with('volontariat_erreur', $e->getMessage());
        }

        return back()->with('succes', 'Votre proposition est retirée.');
    }

    public function repondre(Request $request, Volontariat $volontaire, VolontariatService $volontariat): RedirectResponse
    {
        $accepte = $request->boolean('accepte');

        try {
            $volontariat->repondre($volontaire, $accepte, self::utilisateur($request));
        } catch (DomainException $e) {
            return back()->with('volontariat_erreur', $e->getMessage());
        }

        return back()->with('succes', $accepte ? 'Bienvenue dans la commission '.$volontaire->commission->nom.'.' : 'Invitation déclinée. Le chef est prévenu.');
    }

    /** Le chef de commission accepte ou non un volontaire. */
    public function decider(Request $request, Volontariat $volontaire, VolontariatService $volontariat): RedirectResponse
    {
        $accepte = $request->boolean('accepte');
        $motif = $request->validate(['motif' => ['nullable', 'string', 'max:255']])['motif'] ?? null;

        try {
            $volontariat->decider($volontaire, $accepte, is_string($motif) ? $motif : null, self::utilisateur($request));
        } catch (DomainException $e) {
            return back()->with('comite_erreur', $e->getMessage());
        }

        return back()->with('comite_succes', $volontaire->membre->nom_complet.($accepte ? ' rejoint la commission.' : ' est prévenu que sa proposition n\'est pas retenue.'));
    }

    /** Le chef invite un membre trouvé par ses savoir-faire. */
    public function inviter(Request $request, CommissionComite $commission, Membre $membre, VolontariatService $volontariat): RedirectResponse
    {
        try {
            $volontariat->inviter($commission, $membre, self::utilisateur($request));
        } catch (DomainException $e) {
            return back()->with('comite_erreur', $e->getMessage());
        }

        return back()->with('comite_succes', $membre->nom_complet.' est invité à rejoindre la commission '.$commission->nom.'.');
    }

    private static function utilisateur(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 403);

        return $utilisateur;
    }

    private static function membre(Request $request): Membre
    {
        $membre = self::utilisateur($request)->membre;
        abort_unless($membre !== null && $membre->statut === StatutMembre::Valide, 404);

        return $membre;
    }
}
