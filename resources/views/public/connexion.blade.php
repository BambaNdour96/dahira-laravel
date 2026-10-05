@extends('layouts.public')

@section('titre', 'Connexion')

@section('contenu')
    <h1 class="text-2xl font-semibold">Se connecter</h1>

    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-5 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
        @csrf

        <x-champ nom="telephone" libelle="Téléphone" type="tel" requis inputmode="tel" autocomplete="tel" placeholder="77 123 45 67" />
        <x-champ nom="password" libelle="Mot de passe" type="password" requis autocomplete="current-password" />

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="souvenir" value="1" class="accent-emerald-800">
            Rester connecté sur ce téléphone
        </label>

        <button type="submit" class="w-full rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white hover:bg-emerald-900">
            Se connecter
        </button>
    </form>

    <div class="mt-4 flex justify-between text-sm">
        <a href="{{ route('password.request') }}" class="text-emerald-800 underline">Mot de passe oublié ?</a>
        <a href="{{ route('inscription') }}" class="text-emerald-800 underline">S'inscrire</a>
    </div>
@endsection
