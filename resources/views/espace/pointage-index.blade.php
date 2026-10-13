@extends('layouts.public')

@section('titre', 'Pointage')

@section('contenu')
    <h1 class="text-2xl font-semibold text-emerald-900">Pointage</h1>

    @forelse ($rendezVous as $evenement)
        <a href="{{ route('pointage.show', $evenement) }}" class="mt-4 block rounded-xl bg-white p-4 shadow-sm ring-1 ring-stone-200 hover:bg-stone-50">
            <p class="font-semibold text-emerald-950">{{ $evenement->titre }}</p>
            <p class="text-sm text-stone-600">{{ $evenement->debut->translatedFormat('l j F à H\hi') }} · {{ $evenement->lieu }}</p>
        </a>
    @empty
        <p class="mt-4 rounded-xl bg-white p-6 text-center text-sm text-stone-600 ring-1 ring-stone-200">
            Aucun rendez-vous à pointer en ce moment. Le pointage s'ouvre 3 heures avant le début.
        </p>
    @endforelse
@endsection
