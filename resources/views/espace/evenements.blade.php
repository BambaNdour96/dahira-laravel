@extends('layouts.public')

@section('titre', 'Événements')

@section('contenu')
    <h1 class="text-2xl font-semibold text-emerald-900">Événements de la Daara</h1>

    <h2 class="mt-6 font-semibold text-emerald-900">À venir</h2>
    @if ($aVenir->isEmpty())
        <p class="mt-2 rounded-xl border border-dashed border-stone-300 p-6 text-center text-sm text-stone-500">Aucun événement prévu pour le moment.</p>
    @else
        <ul class="mt-3 space-y-4">
            @foreach ($aVenir as $evenement)
                @include('espace.partials.evenement', ['evenement' => $evenement, 'complet' => true])
            @endforeach
        </ul>
        <p class="mt-3 text-xs text-stone-500">Le jour de l'événement, présentez votre carte de membre à l'entrée : elle sera scannée pour enregistrer votre présence.</p>
    @endif

    <h2 class="mt-8 font-semibold text-emerald-900">Mes participations</h2>
    @if ($participations->isEmpty())
        <p class="mt-2 text-sm text-stone-500">Aucune participation enregistrée pour le moment.</p>
    @else
        <ul class="mt-3 divide-y divide-stone-100 rounded-xl bg-white text-sm ring-1 ring-stone-200">
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
    @endif

    <a href="{{ route('espace') }}" class="mt-6 inline-block text-sm text-emerald-800 underline">Retour à mon compte</a>
@endsection
