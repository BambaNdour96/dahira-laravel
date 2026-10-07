<?php

namespace App\Http\Controllers;

use App\Models\JournalActivite;
use App\Models\User;
use App\Services\CompteBureauService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MotDePasseController extends Controller
{
    public function edit(Request $request): View
    {
        $utilisateur = $request->user();

        return view('espace.mot-de-passe', [
            'obligatoire' => $utilisateur instanceof User && (bool) $utilisateur->getAttribute('doit_changer_mot_de_passe'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 403);

        $donnees = $request->validate([
            'mot_de_passe_actuel' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:mot_de_passe_actuel'],
        ], [
            'mot_de_passe_actuel.required' => 'Indiquez votre mot de passe actuel.',
            'mot_de_passe_actuel.current_password' => 'Le mot de passe actuel est incorrect.',
            'password.required' => 'Choisissez un nouveau mot de passe.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
            'password.different' => 'Le nouveau mot de passe doit être différent de l\'actuel.',
        ]);

        $utilisateur->forceFill([
            'password' => (string) $donnees['password'],
            'doit_changer_mot_de_passe' => false,
        ])->save();

        JournalActivite::enregistrer($utilisateur, 'modifie', ['apres' => ['mot_de_passe' => 'changé par la personne']]);

        $request->session()->regenerate();

        $destination = $utilisateur->hasAnyRole(CompteBureauService::ROLES_BUREAU) ? '/admin' : route('espace');

        return redirect($destination)->with('succes', 'Votre mot de passe a été changé.');
    }
}
