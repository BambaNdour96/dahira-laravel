<?php

namespace App\Http\Controllers;

use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\User;
use App\Services\CotisationService;
use App\Support\LienWave;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CotisationController extends Controller
{
    public function show(Request $request, CotisationService $cotisations): View
    {
        $membre = $this->membre($request);
        $mensuel = $cotisations->montantMensuel();
        $paiements = Paiement::query()->where('membre_id', $membre->id)->where('objet', 'cotisation');

        $liensWave = collect(CotisationService::CHOIX_MOIS)
            ->mapWithKeys(fn (int $nombre): array => [$nombre => LienWave::cotisation($nombre, $mensuel)])
            ->all();

        return view('espace.cotisations', [
            'membre' => $membre,
            'mensuel' => $mensuel,
            'retard' => $cotisations->moisEnRetard($membre),
            'regleJusqua' => $cotisations->regleJusqua($membre),
            'debut' => $cotisations->debut($membre),
            'calendrier' => $cotisations->calendrier($membre, now()->year),
            'choix' => CotisationService::CHOIX_MOIS,
            'liensWave' => $liensWave,
            'lienWave' => collect($liensWave)->filter()->first(),
            'enCours' => (clone $paiements)->where('statut', StatutPaiement::Declare->value)->latest('declare_le')->first(),
            'dernierRefus' => (clone $paiements)->where('statut', StatutPaiement::Refuse->value)->latest('traite_le')->first(),
            'historique' => (clone $paiements)->latest('declare_le')->limit(12)->get(),
        ]);
    }

    public function declarer(Request $request, CotisationService $cotisations): RedirectResponse
    {
        $membre = $this->membre($request);

        $request->validate([
            'nombre_mois' => ['required', 'integer', Rule::in(CotisationService::CHOIX_MOIS)],
        ], [
            'nombre_mois.in' => 'Choisissez 1, 2 ou 3 mois.',
        ]);

        [$reference, $numeroWave] = PaiementMembreController::valider($request);

        try {
            $cotisations->declarer($membre, $reference, (int) $request->input('nombre_mois'), $numeroWave);
        } catch (DomainException $e) {
            return back()->withErrors(['reference' => $e->getMessage()]);
        }

        return redirect()->route('cotisations')
            ->with('succes', 'Merci ! Votre paiement est en cours de vérification par le Jewrine finance.');
    }

    private function membre(Request $request): Membre
    {
        $user = $request->user();
        $membre = $user instanceof User ? $user->membre : null;

        abort_unless($membre !== null && $membre->statut === StatutMembre::Valide, 404);

        return $membre;
    }
}
