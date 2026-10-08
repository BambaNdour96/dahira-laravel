@extends('layouts.public')

@section('titre', 'Mes participations')

@section('contenu')
    <h1 class="text-2xl font-semibold text-emerald-900">Mes participations</h1>
    <p class="mt-1 text-sm text-stone-600">
        Les événements de la Daara où votre présence a été enregistrée, au scan de votre carte de membre.
    </p>

    @if ($participations->isEmpty())
        <p class="mt-6 rounded-xl border border-dashed border-stone-300 p-6 text-center text-sm text-stone-500">
            Aucune participation enregistrée pour le moment. Présentez votre carte à l'entrée des prochains événements.
        </p>
    @else
        <ul class="mt-6 divide-y divide-stone-100 rounded-xl bg-white text-sm ring-1 ring-stone-200">
            @foreach ($participations as $presence)
                <li class="flex items-center justify-between gap-3 px-4 py-3">
                    <span>
                        <span class="font-medium text-emerald-950">{{ $presence->evenement->titre }}</span>
                        <span class="block text-xs text-stone-500">{{ $presence->evenement->debut->translatedFormat('j F Y') }} · {{ $presence->evenement->lieu }}</span>
                    </span>
                    <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-900">Présent ✓</span>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $participations->links() }}</div>
    @endif

    <a href="{{ route('espace') }}" class="mt-6 inline-block text-sm text-emerald-800 underline">Retour à mon compte</a>
@endsection
