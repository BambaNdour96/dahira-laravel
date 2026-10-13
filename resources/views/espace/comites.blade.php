@extends('layouts.public')

@section('titre', 'Mes comités')

@section('contenu')
    <h1 class="text-2xl font-semibold text-emerald-900">Mes comités</h1>

    @if (session('comite_succes'))
        <p class="mt-3 rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-900">{{ session('comite_succes') }}</p>
    @endif
    @if (session('comite_erreur'))
        <p class="mt-3 rounded-lg bg-red-50 p-3 text-sm font-semibold text-red-900">{{ session('comite_erreur') }}</p>
    @endif

    @if ($actions->isNotEmpty())
        <section class="mt-4 rounded-xl bg-amber-50 p-4 ring-1 ring-amber-200">
            <h2 class="font-semibold text-amber-900">Mes actions à mener</h2>
            <ul class="mt-2 space-y-2 text-sm">
                @foreach ($actions as $action)
                    <li class="rounded-lg bg-white p-3 ring-1 {{ $action->estEnRetard() ? 'ring-red-300' : 'ring-amber-200' }}">
                        <span class="font-medium text-stone-900">{{ $action->description }}</span>
                        <span class="block text-xs text-stone-600">
                            {{ $action->instance->nom }}{{ $action->commission ? ' · Commission '.$action->commission->nom : '' }}{{ $action->echeance ? ' · pour le '.$action->echeance->format('d/m/Y') : '' }}
                            · <span class="font-semibold {{ $action->estEnRetard() ? 'text-red-700' : 'text-stone-700' }}">{{ $action->estEnRetard() ? 'En retard' : \App\Models\ActionComite::STATUTS[$action->statut] }}</span>
                        </span>
                        <div class="mt-2 flex gap-2">
                            @if ($action->statut === 'a_faire')
                                <form method="POST" action="{{ route('comites.actions.statut', $action) }}">
                                    @csrf
                                    <input type="hidden" name="statut" value="en_cours">
                                    <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-sky-800 ring-1 ring-sky-300">Commencée</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('comites.actions.statut', $action) }}">
                                @csrf
                                <input type="hidden" name="statut" value="faite">
                                <button type="submit" class="rounded-lg bg-emerald-800 px-3 py-1.5 text-xs font-semibold text-white">Faite ✓</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @forelse ($instances as $instance)
        <section class="mt-5 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            <h2 class="text-lg font-semibold text-emerald-950">{{ $instance->nom }}</h2>
            @if ($instance->commissions->isNotEmpty())
                <p class="mt-1 text-xs text-stone-500">
                    Commissions : {{ $instance->commissions->map(fn ($commission) => $commission->nom.($commission->chef ? ' ('.$commission->chef->prenom.' '.$commission->chef->nom.')' : ''))->implode(' · ') }}
                </p>
            @endif

            <ul class="mt-3 space-y-3">
                @forelse ($instance->reunions as $reunion)
                    <li class="rounded-lg bg-stone-50 p-3 text-sm">
                        <p class="font-semibold text-emerald-900">{{ $reunion->titre }}</p>
                        <p class="text-xs text-stone-600">{{ $reunion->debut->translatedFormat('l j F à H\hi') }} · {{ $reunion->lieu }}</p>
                        @if ($reunion->estPubliee())
                            <a href="{{ route('reunions.pv', $reunion) }}" class="mt-2 inline-block font-semibold text-emerald-800">📄 Lire le PV</a>
                        @elseif ($reunion->points->isNotEmpty())
                            <ol class="mt-2 list-decimal pl-5 text-xs text-stone-700">
                                @foreach ($reunion->points as $point)
                                    <li>{{ $point->titre }}</li>
                                @endforeach
                            </ol>
                        @endif
                    </li>
                @empty
                    <li class="text-sm text-stone-600">Aucune réunion pour le moment.</li>
                @endforelse
            </ul>
        </section>
    @empty
        <p class="mt-4 rounded-xl bg-white p-6 text-center text-sm text-stone-600 ring-1 ring-stone-200">Vous ne faites partie d'aucun comité pour le moment.</p>
    @endforelse
@endsection
