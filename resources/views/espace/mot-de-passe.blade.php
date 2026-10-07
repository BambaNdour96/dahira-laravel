@extends('layouts.public')

@section('titre', 'Changer mon mot de passe')

@section('contenu')
    <h1 class="text-2xl font-semibold text-emerald-900">Changer mon mot de passe</h1>

    @if ($obligatoire)
        <p class="mt-4 rounded-lg bg-or-100 p-4 text-sm text-emerald-950 ring-1 ring-or-400">
            Vous vous êtes connecté avec un <strong>mot de passe provisoire</strong>.
            Pour votre sécurité, choisissez maintenant votre propre mot de passe.
        </p>
    @endif

    <form method="POST" action="{{ route('mot-de-passe.update') }}"
          class="mt-6 space-y-5 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
        @csrf
        @method('PUT')

        <x-champ nom="mot_de_passe_actuel" libelle="{{ $obligatoire ? 'Mot de passe provisoire' : 'Mot de passe actuel' }}"
                 type="password" requis autocomplete="current-password" />
        <x-champ nom="password" libelle="Nouveau mot de passe" type="password" requis autocomplete="new-password"
                 aide="8 caractères minimum." />
        <x-champ nom="password_confirmation" libelle="Confirmez le nouveau mot de passe" type="password" requis
                 autocomplete="new-password" />

        <button type="submit" class="w-full rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white hover:bg-emerald-900">
            Enregistrer mon nouveau mot de passe
        </button>
    </form>

    @unless ($obligatoire)
        <a href="{{ route('espace') }}" class="mt-4 inline-block text-sm text-emerald-800 underline">Retour à mon compte</a>
    @endunless
@endsection
