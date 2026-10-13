@extends('layouts.public')

@section('titre', 'Encaisser')

@section('contenu')
    <a href="{{ route('caisse.index') }}" class="text-sm font-medium text-emerald-800">‹ Ma caisse</a>
    <h1 class="mt-2 text-2xl font-semibold text-emerald-900">{{ $membre->prenom }} {{ $membre->nom }}</h1>
    <p class="text-sm text-stone-600">{{ $membre->matricule }}</p>

    @if (session('caisse_erreur'))
        <p class="mt-3 rounded-lg bg-red-50 p-3 text-sm font-semibold text-red-900">{{ session('caisse_erreur') }}</p>
    @endif

    <section class="mt-5 rounded-xl bg-white p-4 shadow-sm ring-1 ring-stone-200">
        <h2 class="font-semibold text-emerald-900">Cotisation</h2>
        <p class="text-sm text-stone-700">
            {{ $moisEnRetard > 0 ? $moisEnRetard.' mois en retard' : 'À jour' }} · {{ number_format($mensuel, 0, ',', ' ') }} FCFA par mois
        </p>
        <form method="POST" action="{{ route('caisse.cotisation', $membre) }}" class="mt-3 flex items-end gap-2">
            @csrf
            <label class="flex-1 text-xs text-stone-600">Nombre de mois payés
                <input type="number" name="nombre_mois" min="1" max="24" required value="{{ max(1, $moisEnRetard) }}"
                       class="mt-1 block w-full rounded-lg border border-stone-300 px-3 py-2.5 text-base">
            </label>
            <button type="submit" class="rounded-lg bg-emerald-800 px-4 py-3 text-sm font-semibold text-white">Encaisser</button>
        </form>
    </section>

    @foreach ($engagements as $engagement)
        <section class="mt-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-stone-200">
            <h2 class="font-semibold text-emerald-900">{{ $engagement->collecte->titre }}</h2>
            <p class="text-sm text-stone-700">
                {{ $engagement->fixe_par === 'membre' ? 'Jàyante' : 'Sass' }} : {{ number_format($engagement->montant, 0, ',', ' ') }} FCFA
                · versé {{ number_format($engagement->verse(), 0, ',', ' ') }}
                · <span class="font-semibold">reste {{ number_format($engagement->reste(), 0, ',', ' ') }} FCFA</span>
            </p>
            <form method="POST" action="{{ route('caisse.versement', [$membre, $engagement]) }}" class="mt-3 flex items-end gap-2">
                @csrf
                <label class="flex-1 text-xs text-stone-600">Montant reçu (FCFA)
                    <input type="number" name="montant" min="1" max="{{ $engagement->reste() }}" step="50" required value="{{ $engagement->reste() }}"
                           class="mt-1 block w-full rounded-lg border border-stone-300 px-3 py-2.5 text-base">
                </label>
                <button type="submit" class="rounded-lg bg-emerald-800 px-4 py-3 text-sm font-semibold text-white">Encaisser</button>
            </form>
        </section>
    @endforeach

    @if ($engagements->isEmpty())
        <p class="mt-4 text-sm text-stone-600">Aucun Sass ni Jàyante en cours pour ce membre.</p>
    @endif
@endsection
