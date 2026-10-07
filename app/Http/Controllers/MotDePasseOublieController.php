<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TelephoneNormalizer;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Mot de passe oublié : un lien par email pour ceux qui ont une adresse,
 * sinon le membre contacte son Jewrine de cellule.
 */
class MotDePasseOublieController extends Controller
{
    public function create(): View
    {
        return view('public.mot-de-passe-oublie');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['telephone' => ['required', 'string']], [
            'telephone.required' => 'Indiquez votre numéro de téléphone.',
        ]);

        $telephone = TelephoneNormalizer::normaliser((string) $request->input('telephone'));
        $utilisateur = $telephone !== null ? User::query()->where('telephone', $telephone)->first() : null;

        if ($utilisateur !== null && filled($utilisateur->email) && (bool) $utilisateur->actif) {
            Password::sendResetLink(['email' => $utilisateur->email]);
        }

        // Même message dans tous les cas, pour ne pas révéler quels numéros sont inscrits.
        return back()->with('succes', 'Si ce numéro correspond à un compte avec une adresse email, '
            .'un lien vient de vous être envoyé. Sinon, contactez votre Jewrine de cellule.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('public.reinitialiser-mot-de-passe', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', \Illuminate\Validation\Rules\Password::defaults(), 'confirmed'],
        ], [
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
        ]);

        $statut = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $utilisateur, string $motDePasse): void {
                $utilisateur->forceFill([
                    'password' => $motDePasse,
                    'doit_changer_mot_de_passe' => false,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($utilisateur));
            },
        );

        if ($statut !== Password::PASSWORD_RESET) {
            return back()->withErrors(['password' => 'Ce lien n\'est plus valable. Recommencez depuis « Mot de passe oublié ».']);
        }

        return redirect()->route('login')->with('succes', 'Votre mot de passe a été changé. Vous pouvez vous connecter.');
    }
}
