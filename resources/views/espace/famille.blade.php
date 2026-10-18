@extends('layouts.public')

@section('titre', 'Ma famille')

@section('contenu')
    <h1 class="text-2xl font-semibold text-emerald-900">Ma famille</h1>
    <p class="mt-1 text-sm text-stone-600">
        Les fiches de vos proches : vos enfants, ou un proche sans téléphone. Vous voyez leurs cartes et vous payez pour eux.
    </p>

    @forelse ($proches as $proche)
        @php
            $enRetard = $retards[$proche->id] ?? 0;
        @endphp
        <a href="{{ route('famille.show', $proche) }}" class="mt-4 flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-stone-200 hover:bg-stone-100">
            @if ($proche->photo_path)
                <img src="{{ route('photos.show', $proche) }}" alt="" class="h-12 w-12 shrink-0 rounded-full object-cover ring-2 ring-or-400">
            @endif
            <div class="min-w-0 flex-1">
                <p class="truncate font-semibold text-emerald-950">{{ $proche->prenom }} {{ $proche->nom }}</p>
                <p class="truncate text-xs text-stone-600">
                    {{ $proche->lien_famille?->getLabel() }}{{ $proche->date_naissance ? ' · '.$proche->date_naissance->age.' ans' : '' }}{{ $proche->matricule ? ' · '.$proche->matricule : '' }}
                </p>
                <span class="mt-1 inline-block rounded-full bg-stone-100 px-2 py-0.5 text-xs font-semibold text-stone-700">{{ $proche->statut->getLabel() }}</span>
                @if ($enRetard > 0)
                    <span class="mt-1 inline-block rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-900">Cotisation : {{ $enRetard }} mois de retard</span>
                @endif
            </div>
            <span class="shrink-0 text-emerald-800" aria-hidden="true">›</span>
        </a>
    @empty
        <div class="mt-6 rounded-xl bg-white p-5 text-sm text-stone-600 shadow-sm ring-1 ring-stone-200">
            Aucun proche pour l'instant.
        </div>
    @endforelse

    <a href="{{ route('famille.create') }}" class="mt-6 block rounded-lg bg-emerald-800 px-5 py-3 text-center font-semibold text-white hover:bg-emerald-900">
        Ajouter un proche
    </a>

    <a href="{{ route('espace') }}" class="mt-6 inline-block text-sm text-emerald-800 underline">Retour à mon compte</a>
@endsection
