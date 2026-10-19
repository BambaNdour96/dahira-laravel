<?php

namespace App\Http\Controllers;

use App\Services\ActivationCompteService;
use App\Services\TelephoneNormalizer;
use App\Support\MessagesValidation;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** « Activer mon compte » : un membre dont la fiche existe déjà (reprise des listes) ouvre son compte. */
class ActivationController extends Controller
{
    public function create(): View
    {
        return view('public.activation');
    }

    public function store(Request $request, ActivationCompteService $activations): RedirectResponse
    {
        $request->merge(['telephone' => TelephoneNormalizer::normaliser((string) $request->input('telephone')) ?? $request->input('telephone')]);
        $donnees = $request->validate([
            'telephone' => ['required', 'string', 'max:20'],
            'date_naissance' => ['required', 'date', 'before:today'],
            'matricule' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ], MessagesValidation::tous());

        try {
            $resultat = $activations->demander((string) $donnees['telephone'], (string) $donnees['date_naissance'], isset($donnees['matricule']) ? (string) $donnees['matricule'] : null, (string) $donnees['password']);
        } catch (DomainException $e) {
            return back()->withInput($request->except(['password', 'password_confirmation']))->withErrors(['telephone' => $e->getMessage()]);
        }

        return $resultat === 'active'
            ? redirect()->route('login')->with('succes', 'Votre compte est activé : connectez-vous avec votre numéro et votre mot de passe.')
            : redirect()->route('login')->with('succes', 'Votre demande est envoyée à votre Jewrine de cellule. Dès qu\'il l\'aura validée, connectez-vous avec le mot de passe choisi.');
    }
}
