@extends('layouts.public')

@section('titre', 'Nouveau mot de passe')

@section('contenu')
    <h1 class="text-2xl font-semibold text-emerald-900">Choisir un nouveau mot de passe</h1>

    <form method="POST" action="{{ route('password.update') }}"
          class="mt-6 space-y-5 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ $email }}">

        <x-champ nom="password" libelle="Nouveau mot de passe" type="password" requis autocomplete="new-password"
                 aide="8 caractères minimum." />
        <x-champ nom="password_confirmation" libelle="Confirmez le mot de passe" type="password" requis autocomplete="new-password" />

        <button type="submit" class="w-full rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white hover:bg-emerald-900">
            Enregistrer
        </button>
    </form>
@endsection
