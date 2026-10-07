@extends('layouts.public')

@section('titre', 'Mot de passe oublié')

@section('contenu')
    <h1 class="text-2xl font-semibold text-emerald-900">Mot de passe oublié</h1>
    <p class="mt-2 text-sm text-stone-600">
        Indiquez votre numéro de téléphone. Si vous avez renseigné une adresse email,
        vous recevrez un lien pour choisir un nouveau mot de passe.
    </p>

    <form method="POST" action="{{ route('password.email') }}"
          class="mt-6 space-y-5 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
        @csrf

        <x-champ nom="telephone" libelle="Téléphone" type="tel" requis inputmode="tel" autocomplete="tel" placeholder="77 123 45 67" />

        <button type="submit" class="w-full rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white hover:bg-emerald-900">
            Recevoir un lien
        </button>
    </form>

    <div class="mt-6 rounded-xl bg-or-100 p-4 text-sm text-emerald-950 ring-1 ring-or-400">
        <p class="font-semibold">Pas d'adresse email ?</p>
        <p class="mt-1">
            Contactez votre Jewrine de cellule : il vous donnera un mot de passe provisoire,
            que vous changerez à votre prochaine connexion.
        </p>
    </div>

    <a href="{{ route('login') }}" class="mt-4 inline-block text-sm text-emerald-800 underline">Retour à la connexion</a>
@endsection
