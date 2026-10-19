@extends('layouts.public')

@section('titre', 'Activer mon compte')

@section('contenu')
    <h1 class="text-2xl font-semibold">Activer mon compte</h1>
    <p class="mt-1 text-sm text-stone-600">
        Votre fiche de membre existe déjà (votre Jewrine vous a inscrit, ou la Daara a repris ses listes), mais vous ne vous
        êtes jamais connecté. Avec votre matricule, le compte s'ouvre aussitôt ; sans lui, votre Jewrine de cellule valide
        votre demande.
    </p>

    <form method="POST" action="{{ route('activation.store') }}" class="mt-6 space-y-5 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
        @csrf
        <x-champ nom="telephone" libelle="Téléphone de la fiche" type="tel" requis inputmode="tel" autocomplete="tel" placeholder="77 123 45 67" />
        <x-date-naissance />
        <x-champ nom="matricule" libelle="Matricule (facultatif)" placeholder="NDK-2019-0001" aide="Il figure sur votre carte de membre." />
        <x-champ nom="password" libelle="Mot de passe choisi" type="password" requis autocomplete="new-password" aide="8 caractères au moins, avec des lettres et des chiffres." />
        <x-champ nom="password_confirmation" libelle="Mot de passe, encore une fois" type="password" requis autocomplete="new-password" />

        <button type="submit" class="w-full rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white hover:bg-emerald-900">
            Activer mon compte
        </button>
    </form>

    <a href="{{ route('login') }}" class="mt-4 inline-block text-sm text-emerald-800 underline">Retour à la connexion</a>
@endsection
