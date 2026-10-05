@extends('layouts.public')

@section('titre', 'Accueil')

@section('contenu')
    <h1 class="text-3xl font-semibold leading-tight">Bienvenue dans la plateforme des membres</h1>
    <p class="mt-3 text-stone-600">
        Inscrivez-vous en ligne pour rejoindre la Dahira Nouroud Dareyni et obtenir votre carte de membre.
    </p>

    <div class="mt-6 flex flex-col gap-3 sm:flex-row">
        @guest
            <a href="{{ route('inscription') }}" class="rounded-lg bg-emerald-800 px-5 py-3 text-center font-semibold text-white hover:bg-emerald-900">S'inscrire</a>
            <a href="{{ route('login') }}" class="rounded-lg border border-emerald-800 px-5 py-3 text-center font-semibold text-emerald-900 hover:bg-emerald-50">Se connecter</a>
        @else
            <a href="{{ route('espace') }}" class="rounded-lg bg-emerald-800 px-5 py-3 text-center font-semibold text-white hover:bg-emerald-900">Mon espace</a>
        @endguest
    </div>

    <ol class="mt-10 space-y-4">
        <li class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-stone-200">
            <p class="font-semibold">1. Inscription</p>
            <p class="text-sm text-stone-600">Remplissez le formulaire avec votre photo, depuis votre téléphone.</p>
        </li>
        <li class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-stone-200">
            <p class="font-semibold">2. Validation</p>
            <p class="text-sm text-stone-600">Votre responsable de section vérifie votre demande et vous attribue un matricule.</p>
        </li>
        <li class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-stone-200">
            <p class="font-semibold">3. Carte de membre</p>
            <p class="text-sm text-stone-600">Votre carte avec QR code sera disponible dans votre espace.</p>
        </li>
    </ol>
@endsection
