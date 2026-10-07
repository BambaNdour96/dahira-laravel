<?php

namespace App\Http\Controllers;

use App\Models\JournalActivite;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
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

    /** Après le changement, la personne est déconnectée partout et se reconnecte avec son nouveau mot de passe. */
    public function update(Request $request): RedirectResponse
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 403);

        $donnees = $request->validate([
            'mot_de_passe_actuel' => ['required', 'current_password'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed', 'different:mot_de_passe_actuel'],
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

        // Toutes les connexions ouvertes sont fermées, y compris celle-ci.
        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $utilisateur->id)->delete();
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('succes', 'Votre mot de passe a été changé. Reconnectez-vous avec votre nouveau mot de passe.');
    }
}
