@extends('layouts.public')

@section('titre', 'Demande envoyée')

@section('contenu')
    <div class="rounded-xl bg-white p-6 text-center shadow-sm ring-1 ring-stone-200">
        <p class="text-4xl">✅</p>
        <h1 class="mt-3 text-2xl font-semibold">Merci {{ auth()->user()?->membre?->prenom }} !</h1>
        <p class="mt-3 text-stone-600">
            Votre demande d'adhésion est enregistrée. Votre responsable de cellule va la vérifier.
        </p>
        <a href="{{ route('espace') }}" class="mt-6 inline-block rounded-lg bg-emerald-800 px-5 py-3 font-semibold text-white hover:bg-emerald-900">
            Suivre ma demande
        </a>
    </div>
@endsection
