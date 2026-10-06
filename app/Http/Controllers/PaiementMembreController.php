<?php

namespace App\Http\Controllers;

use App\Enums\StatutMembre;
use App\Models\Membre;
use App\Models\User;
use App\Services\PaiementService;
use App\Services\StatutMembreService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaiementMembreController extends Controller
{
    /** Le membre déclare son paiement Wave avec la référence de son reçu. */
    public function declarer(Request $request, PaiementService $paiements): RedirectResponse
    {
        $membre = $this->membre($request);

        $request->merge([
            'reference' => strtoupper(preg_replace('/\s+/', '', (string) $request->input('reference')) ?? ''),
        ]);

        $donnees = $request->validate([
            'reference' => ['required', 'string', 'min:4', 'max:60', 'unique:paiements,reference'],
        ], [
            'reference.required' => 'Indiquez la référence de votre transaction Wave.',
            'reference.min' => 'Cette référence est trop courte.',
            'reference.max' => 'Cette référence est trop longue.',
            'reference.unique' => 'Cette référence a déjà été utilisée.',
        ]);

        try {
            $paiements->declarer($membre, (string) $donnees['reference']);
        } catch (DomainException $e) {
            return back()->withErrors(['reference' => $e->getMessage()]);
        }

        return redirect()->route('espace')
            ->with('succes', 'Merci ! Votre paiement est en cours de vérification par le trésorier.');
    }

    /** Après expiration, le membre renvoie sa demande à son Jewrine. */
    public function relancer(Request $request, StatutMembreService $statuts): RedirectResponse
    {
        $membre = $this->membre($request);

        abort_unless($membre->statut === StatutMembre::Expire, 403);

        $auteur = $request->user();
        $statuts->changer($membre, StatutMembre::EnAttente, $auteur instanceof User ? $auteur : null);

        return redirect()->route('espace')
            ->with('succes', 'Votre demande a été renvoyée à votre Jewrine de cellule.');
    }

    private function membre(Request $request): Membre
    {
        $user = $request->user();
        $membre = $user instanceof User ? $user->membre : null;

        abort_if($membre === null, 404);

        return $membre;
    }
}
