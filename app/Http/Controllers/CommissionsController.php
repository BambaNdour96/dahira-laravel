<?php

namespace App\Http\Controllers;

use App\Models\AppartenanceCommission;
use App\Models\Commission;
use App\Models\User;
use App\Services\ComiteService;
use App\Services\CommissionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Sur le téléphone, le Jewrine d'une commission permanente gère ses membres et les souhaits reçus. */
class CommissionsController extends Controller
{
    public function ajouter(Request $request, Commission $commission, CommissionService $commissions): RedirectResponse
    {
        $saisie = (string) $request->validate(['membre' => ['required', 'string', 'max:30']])['membre'];
        $membre = ComiteService::trouverMembre($saisie);

        if ($membre === null) {
            return back()->with('comite_erreur', 'Aucun membre validé avec ce matricule ou ce numéro.');
        }

        return $this->executer(
            fn () => $commissions->ajouter($commission, $membre, $this->utilisateur($request)),
            $membre->prenom.' '.$membre->nom.' rejoint la commission '.$commission->nom.'.',
        );
    }

    public function accepter(Request $request, AppartenanceCommission $appartenance, CommissionService $commissions): RedirectResponse
    {
        return $this->executer(
            fn () => $commissions->accepter($appartenance, $this->utilisateur($request)),
            $appartenance->membre->prenom.' '.$appartenance->membre->nom.' rejoint la commission.',
        );
    }

    public function refuser(Request $request, AppartenanceCommission $appartenance, CommissionService $commissions): RedirectResponse
    {
        $motif = (string) ($request->validate(['motif' => ['nullable', 'string', 'max:255']])['motif'] ?? '');

        return $this->executer(
            fn () => $commissions->refuser($appartenance, $motif, $this->utilisateur($request)),
            'Souhait refusé : le membre est prévenu.',
        );
    }

    public function retirer(Request $request, AppartenanceCommission $appartenance, CommissionService $commissions): RedirectResponse
    {
        $motif = (string) $request->validate(['motif' => ['required', 'string', 'max:255']])['motif'];

        return $this->executer(
            fn () => $commissions->retirer($appartenance, $motif, $this->utilisateur($request)),
            $appartenance->membre->prenom.' '.$appartenance->membre->nom.' ne fait plus partie de la commission.',
        );
    }

    private function executer(\Closure $operation, string $succes): RedirectResponse
    {
        try {
            $operation();
        } catch (DomainException $e) {
            return back()->with('comite_erreur', $e->getMessage());
        }

        return back()->with('comite_succes', $succes);
    }

    private function utilisateur(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 403);

        return $utilisateur;
    }
}
