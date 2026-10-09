@extends('layouts.public')

@section('titre', 'Connexion')

@section('contenu')
    <h1 class="text-2xl font-semibold">Se connecter</h1>

    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-5 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
        @csrf

        @php($numeroMemorise = request()->cookie(\App\Listeners\MemoriserNumero::COOKIE))

        @if (is_string($numeroMemorise) && $numeroMemorise !== '' && ! $errors->has('telephone'))

            <input type="hidden" name="numero_memorise" value="1">

            <div class="rounded-lg bg-emerald-50 px-4 py-3 ring-1 ring-emerald-200">

                <p class="text-xs text-stone-500">Numéro</p>

                <p class="text-lg font-semibold tracking-wider text-emerald-950">****{{ substr($numeroMemorise, -3) }}</p>

                <a href="{{ route('connexion.autre-numero') }}" class="mt-1 inline-block text-sm text-emerald-800 underline">Utiliser un autre numéro</a>

            </div>

        @else

        <x-indicatif />

        <x-champ nom="telephone" libelle="Téléphone" type="tel" requis inputmode="tel" autocomplete="tel" placeholder="77 123 45 67" />

        @endif
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
    <div data-empreinte class="mt-6 hidden">
        <div class="flex items-center gap-3 text-xs text-stone-500">
            <span class="h-px flex-1 bg-stone-200"></span> ou <span class="h-px flex-1 bg-stone-200"></span>
        </div>
        <button type="button" data-connexion-empreinte data-options="{{ route('empreinte.options-connexion') }}" data-connecter="{{ route('empreinte.connexion') }}"
                data-jeton="{{ csrf_token() }}" data-message="message-connexion-empreinte"
                class="mt-4 w-full rounded-lg bg-white px-4 py-3 font-semibold text-emerald-900 ring-1 ring-stone-300 hover:bg-stone-100">
            🔐 Se connecter avec l'empreinte
        </button>
        <p id="message-connexion-empreinte" class="mt-2 text-sm"></p>
    </div>

@endsection
