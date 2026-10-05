@extends('layouts.public')

@section('titre', 'Mon espace')

@section('contenu')
    @if (! $membre)
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            <p>Ce compte n'est rattaché à aucune fiche de membre.</p>
            <a href="/admin" class="mt-3 inline-block text-sm font-medium text-emerald-800 underline">Aller au panneau d'administration</a>
        </div>
    @else
        @php
            $classes = match ($membre->statut) {
                \App\Enums\StatutMembre::Valide => 'bg-emerald-100 text-emerald-900',
                \App\Enums\StatutMembre::EnAttente => 'bg-amber-100 text-amber-900',
                \App\Enums\StatutMembre::ACorriger => 'bg-sky-100 text-sky-900',
                \App\Enums\StatutMembre::Suspendu => 'bg-stone-200 text-stone-800',
                default => 'bg-red-100 text-red-900',
            };
        @endphp

        <div class="flex items-center gap-4">
            @if ($membre->photo_path)
                <img src="{{ route('photos.show', $membre) }}" alt="Photo de {{ $membre->prenom }}"
                     class="h-20 w-20 rounded-full object-cover shadow ring-2 ring-white">
            @endif
            <div>
                <h1 class="text-2xl font-semibold">{{ $membre->prenom }} {{ $membre->nom }}</h1>
                <p class="text-sm text-stone-600">Section {{ $membre->section->nom }}</p>
            </div>
        </div>

        <div class="mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            <p class="text-sm text-stone-500">Statut de votre adhésion</p>
            <p class="mt-2 inline-block rounded-full px-3 py-1 text-sm font-semibold {{ $classes }}">
                {{ $membre->statut->getLabel() }}
            </p>

            @if ($membre->statut === \App\Enums\StatutMembre::EnAttente)
                <p class="mt-4 text-sm text-stone-700">Votre demande est en cours de vérification par votre responsable de section.</p>
            @elseif ($membre->statut === \App\Enums\StatutMembre::ACorriger)
                <p class="mt-4 text-sm text-stone-700">Votre responsable de section vous demande de corriger votre fiche :</p>
                <p class="mt-2 rounded-lg bg-sky-50 p-3 text-sm italic text-sky-900">{{ $membre->motif_statut }}</p>
                <a href="{{ route('espace.profil') }}" class="mt-4 inline-block rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900">Corriger ma fiche</a>
            @elseif ($membre->statut === \App\Enums\StatutMembre::Rejete)
                <p class="mt-4 text-sm text-stone-700">Votre demande n'a pas été retenue :</p>
                <p class="mt-2 rounded-lg bg-red-50 p-3 text-sm italic text-red-900">{{ $membre->motif_statut }}</p>
            @elseif ($membre->statut === \App\Enums\StatutMembre::Valide)
                <p class="mt-4 text-sm text-stone-700">Votre matricule : <span class="font-mono font-semibold">{{ $membre->matricule }}</span></p>
                <p class="mt-2 text-sm text-stone-500">Votre carte de membre et le paiement de la cotisation seront bientôt disponibles ici.</p>
            @else
                <p class="mt-4 text-sm text-stone-700">Pour toute question, contactez le bureau de la dahira.</p>
            @endif
        </div>

        @if ($membre->statut !== \App\Enums\StatutMembre::ACorriger)
            <a href="{{ route('espace.profil') }}" class="mt-6 inline-block text-sm font-medium text-emerald-800 underline">Modifier mon email ou ma photo</a>
        @endif
    @endif
@endsection
