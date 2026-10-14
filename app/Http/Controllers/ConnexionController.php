<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TelephoneNormalizer;
use App\Support\MessagesValidation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConnexionController extends Controller
{
    public function create(): View
    {
        return view('public.connexion');
    }

    public function store(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'telephone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], MessagesValidation::tous());

        $telephone = TelephoneNormalizer::normaliser($donnees['telephone']) ?? $donnees['telephone'];

        $identifiants = ['telephone' => $telephone, 'password' => $donnees['password'], 'actif' => true];

        if (! Auth::attempt($identifiants, $request->boolean('souvenir'))) {
            throw ValidationException::withMessages([
                'telephone' => 'Téléphone ou mot de passe incorrect.',
            ]);
        }

        $request->session()->regenerate();

        $user = $request->user();
        $estDuBureau = $user instanceof User && $user->can('panneau.accueil');

        return redirect()->intended($estDuBureau ? '/admin' : route('espace'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('accueil');
    }
}
