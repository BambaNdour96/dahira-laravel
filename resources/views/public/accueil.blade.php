@extends('layouts.public')

@section('titre', 'Accueil')

@section('contenu')
    <section class="overflow-hidden rounded-2xl bg-linear-to-br from-emerald-800 to-emerald-950 text-white shadow-lg">
        <div class="flex flex-col items-center px-6 py-10 text-center">
            <img src="{{ asset('images/logo-ndk.png') }}" alt=""
                 class="h-28 w-28 rounded-full bg-white object-cover p-1 shadow-md ring-4 ring-or-400">
            <h1 class="mt-5 text-2xl leading-tight font-bold sm:text-3xl">Daara Nouroud Daareyni Kaolack</h1>
            <p class="mt-2 text-sm font-medium text-or-200">Bienvenue dans la plateforme des membres</p>
            <p class="mt-4 max-w-sm text-emerald-50">
                Inscrivez-vous en ligne pour rejoindre la daara et obtenir votre carte de membre.
            </p>

            <div class="mt-6 flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                @guest
                    <a href="{{ route('inscription') }}" class="rounded-lg bg-or-500 px-6 py-3 font-semibold text-emerald-950 shadow hover:bg-or-400">S'inscrire</a>
                    <a href="{{ route('login') }}" class="rounded-lg border border-white/40 px-6 py-3 font-semibold text-white hover:bg-white/10">Se connecter</a>
                @else
                    <a href="{{ route('espace') }}" class="rounded-lg bg-or-500 px-6 py-3 font-semibold text-emerald-950 shadow hover:bg-or-400">Mon espace</a>
                @endguest
            </div>
        </div>
    </section>

    <h2 class="mt-10 text-lg font-semibold text-emerald-900">Comment ça marche</h2>
    <ol class="mt-4 space-y-3">
        @foreach ([
            ['Inscription', 'Remplissez le formulaire avec votre photo, depuis votre téléphone.'],
            ['Validation', 'Votre responsable de cellule vérifie votre demande et vous attribue un matricule.'],
            ['Carte de membre', 'Votre carte avec QR code sera disponible dans votre espace.'],
        ] as $i => [$titre, $texte])
            <li class="flex gap-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-stone-200">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-or-100 font-bold text-emerald-900 ring-1 ring-or-400">{{ $i + 1 }}</span>
                <div>
                    <p class="font-semibold">{{ $titre }}</p>
                    <p class="text-sm text-stone-600">{{ $texte }}</p>
                </div>
            </li>
        @endforeach
    </ol>
@endsection
