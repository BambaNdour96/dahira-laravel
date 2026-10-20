<?php

namespace App\Http\Controllers;

use App\Models\Membre;
use App\Models\Parametre;
use App\Models\Renouvellement;
use App\Models\User;
use App\Services\PaiementService;
use App\Services\RenouvellementService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Le renouvellement sur le téléphone : vérifier la fiche, puis payer ; pour soi, ou pour un proche (« Ma famille »). */
class RenouvellementController extends Controller
{
    public function show(Request $request, RenouvellementService $renouvellements, PaiementService $paiements, ?Membre $proche = null): View
    {
        $membre = $this->membre($request, $proche);

        return view('espace.renouvellement', [
            'membre' => $membre->load('cellule'),
            'proche' => $proche,
            'renouvellement' => $renouvellements->pour($membre),
            'montant' => $paiements->montantFraisCarte(),
            'lienWave' => Parametre::valeur('wave_lien_frais_carte') ?: Parametre::valeur('wave_lien_paiement'),
            'enCours' => $renouvellements->paiementEnCours($membre),
        ]);
    }

    public function verifier(Request $request, RenouvellementService $renouvellements, ?Membre $proche = null): RedirectResponse
    {
        return $this->executer($request, $proche, fn (Renouvellement $renouvellement, User $auteur) => $renouvellements->verifierFiche($renouvellement, $auteur), 'Merci. Il reste à régler les frais de renouvellement.');
    }

    public function signaler(Request $request, RenouvellementService $renouvellements, ?Membre $proche = null): RedirectResponse
    {
        $message = (string) ($request->validate(['message' => ['required', 'string', 'max:300']], ['message.required' => 'Indiquez ce qu\'il faut corriger.'])['message'] ?? '');

        return $this->executer($request, $proche, fn (Renouvellement $renouvellement, User $auteur) => $renouvellements->signalerErreur($renouvellement, $message, $auteur), 'Votre Jewrine de cellule est prévenu : il corrigera la fiche.');
    }

    public function declarer(Request $request, RenouvellementService $renouvellements, ?Membre $proche = null): RedirectResponse
    {
        [$reference, $numeroWave, $moyen] = PaiementMembreController::valider($request);

        return $this->executer($request, $proche, fn (Renouvellement $renouvellement, User $auteur) => $renouvellements->declarer($renouvellement, $reference, $numeroWave, $auteur, $moyen), 'Merci ! Le paiement est en cours de vérification par le Jewrine finance.');
    }

    /** @param  callable(Renouvellement, User): mixed  $operation */
    private function executer(Request $request, ?Membre $proche, callable $operation, string $succes): RedirectResponse
    {
        $membre = $this->membre($request, $proche);
        $renouvellement = app(RenouvellementService::class)->pour($membre);
        $auteur = $request->user();
        abort_unless($renouvellement !== null && $auteur instanceof User, 404);

        try {
            $operation($renouvellement, $auteur);
        } catch (DomainException $e) {
            return back()->withErrors(['renouvellement' => $e->getMessage()]);
        }

        return redirect()->to($proche !== null ? route('famille.renouvellement', $proche) : route('renouvellement.show'))->with('succes', $succes);
    }

    private function membre(Request $request, ?Membre $proche): Membre
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        if ($proche !== null) {
            FamilleController::verifier($user, $proche);

            return $proche;
        }

        $membre = $user->membre;
        abort_if($membre === null, 404);

        return $membre;
    }
}
