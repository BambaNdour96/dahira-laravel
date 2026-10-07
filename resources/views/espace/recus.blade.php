@extends('layouts.public')

@section('titre', 'Mes reçus')

@section('contenu')
    @php
        $fcfa = fn (int $montant): string => number_format($montant, 0, ',', ' ').' FCFA';
        $filtres = ['' => 'Tous', 'inscription' => 'Inscription', 'cotisation' => 'Cotisations'];
    @endphp

    <h1 class="text-2xl font-semibold text-emerald-900">Mes reçus</h1>
    <p class="mt-1 text-sm text-stone-600">Total réglé depuis votre adhésion : <strong>{{ $fcfa($total) }}</strong>.</p>

    {{-- Filtres --}}
    <form method="GET" action="{{ route('recus.index') }}" class="mt-6 flex flex-wrap items-center gap-2 text-sm">
        @foreach ($filtres as $valeur => $libelle)
            <a href="{{ route('recus.index', array_filter(['type' => $valeur, 'annee' => $annee])) }}"
               class="rounded-full px-4 py-1.5 font-medium {{ (string) $type === $valeur ? 'bg-emerald-800 text-white' : 'bg-white text-stone-700 ring-1 ring-stone-300 hover:bg-stone-50' }}">
                {{ $libelle }}
            </a>
        @endforeach

        @if ($annees->count() > 1)
            @if ($type)
                <input type="hidden" name="type" value="{{ $type }}">
            @endif
            <select name="annee" onchange="this.form.submit()"
                    class="ml-auto rounded-lg border border-stone-300 bg-white px-3 py-1.5">
                <option value="">Toutes les années</option>
                @foreach ($annees as $choixAnnee)
                    <option value="{{ $choixAnnee }}" @selected($annee === $choixAnnee)>{{ $choixAnnee }}</option>
                @endforeach
            </select>
        @endif
    </form>

    {{-- Liste --}}
    @if ($recus->isEmpty())
        <div class="mt-6 rounded-xl border border-dashed border-stone-300 p-6 text-center text-sm text-stone-500">
            Aucun reçu pour le moment.
        </div>
    @else
        <ul class="mt-6 space-y-3">
            @foreach ($recus as $recu)
                <li class="flex items-center gap-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-stone-200">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $recu->estCotisation() ? 'bg-emerald-100 text-emerald-900' : 'bg-or-100 text-emerald-950' }}">
                                {{ $recu->natureRecu() }}
                            </span>
                            <span class="font-mono text-xs text-stone-500">{{ $recu->numero_recu }}</span>
                        </div>
                        <p class="mt-1 font-semibold text-emerald-950">
                            {{ $recu->periode() ? ucfirst($recu->periode()) : $recu->libelleObjet() }}
                        </p>
                        <p class="text-xs text-stone-500">
                            {{ $fcfa($recu->montant) }} · {{ $recu->moyen->getLabel() }} · le {{ $recu->traite_le?->format('d/m/Y') }}
                        </p>
                    </div>
                    <a href="{{ route('recus.show', $recu) }}" target="_blank"
                       class="shrink-0 rounded-lg bg-emerald-800 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-900">
                        Télécharger
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">
            {{ $recus->links() }}
        </div>
    @endif

    <a href="{{ route('espace') }}" class="mt-6 inline-block text-sm text-emerald-800 underline">Retour à mon compte</a>
@endsection
