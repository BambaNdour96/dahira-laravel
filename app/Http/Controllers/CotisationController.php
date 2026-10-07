<?php

namespace App\Http\Controllers;

use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\Parametre;
use App\Models\User;
use App\Services\CotisationService;
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
        $paiements = Paiement::query()->where('membre_id', $membre->id)->where('objet', 'cotisation');

        return view('espace.cotisations', [
            'membre' => $membre,
            'mensuel' => $cotisations->montantMensuel(),
            'retard' => $cotisations->moisEnRetard($membre),
            'regleJusqua' => $cotisations->regleJusqua($membre),
            'debut' => $cotisations->debut($membre),
            'calendrier' => $cotisations->calendrier($membre, now()->year),
            'choix' => CotisationService::CHOIX_MOIS,
            'lienWave' => Parametre::valeur('wave_lien_paiement'),
            'enCours' => (clone $paiements)->where('statut', StatutPaiement::Declare->value)->latest('declare_le')->first(),
            'dernierRefus' => (clone $paiements)->where('statut', StatutPaiement::Refuse->value)->latest('traite_le')->first(),
            'historique' => (clone $paiements)->latest('declare_le')->limit(12)->get(),
        ]);
    }

    public function declarer(Request $request, CotisationService $cotisations): RedirectResponse
    {
        $membre = $this->membre($request);

        $request->merge([
            'reference' => strtoupper(preg_replace('/\s+/', '', (string) $request->input('reference')) ?? ''),
        ]);

        $donnees = $request->validate([
            'reference' => ['required', 'string', 'min:4', 'max:60', 'unique:paiements,reference'],
            'nombre_mois' => ['required', 'integer', Rule::in(CotisationService::CHOIX_MOIS)],
        ], [
            'reference.required' => 'Indiquez la référence de votre transaction Wave.',
            'reference.min' => 'Cette référence est trop courte.',
            'reference.max' => 'Cette référence est trop longue.',
            'reference.unique' => 'Cette référence a déjà été utilisée.',
            'nombre_mois.in' => 'Choisissez 1, 3, 6 ou 12 mois.',
        ]);

        try {
            $cotisations->declarer($membre, (string) $donnees['reference'], (int) $donnees['nombre_mois']);
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
