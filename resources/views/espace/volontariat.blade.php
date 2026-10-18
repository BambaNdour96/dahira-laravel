@extends('layouts.public')

@section('titre', 'Se porter volontaire')

@section('contenu')
    <h1 class="text-2xl font-semibold text-emerald-900">Se porter volontaire</h1>
    <p class="mt-1 text-sm text-stone-600">Proposez votre aide à une commission d'un grand événement de la Daara ; son chef vous répond.</p>

    @if (session('volontariat_erreur'))
        <p class="mt-3 rounded-lg bg-red-50 p-3 text-sm font-semibold text-red-900">{{ session('volontariat_erreur') }}</p>
    @endif

    @foreach ($invitations as $invitation)
        <div class="mt-4 rounded-xl bg-or-100 p-4 text-sm ring-1 ring-or-400">
            <p class="font-semibold text-emerald-950">Invitation : commission {{ $invitation->commission->nom }}</p>
            <p class="text-xs text-stone-600">{{ $invitation->commission->instance->nom }}</p>
            <div class="mt-3 flex gap-2">
                <form method="POST" action="{{ route('volontariat.repondre', $invitation) }}">
                    @csrf
                    <input type="hidden" name="accepte" value="1">
                    <button type="submit" class="rounded-lg bg-emerald-800 px-4 py-2 font-semibold text-white">J'accepte</button>
                </form>
                <form method="POST" action="{{ route('volontariat.repondre', $invitation) }}">
                    @csrf
                    <input type="hidden" name="accepte" value="0">
                    <button type="submit" class="rounded-lg bg-white px-4 py-2 font-semibold text-stone-700 ring-1 ring-stone-300">Je décline</button>
                </form>
            </div>
        </div>
    @endforeach

    @forelse ($commissions as $comite => $liste)
        <section class="mt-5 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            <h2 class="text-lg font-semibold text-emerald-950">{{ $comite }}</h2>
            <ul class="mt-3 space-y-4">
                @foreach ($liste as $commission)
                    <li class="border-t border-stone-100 pt-3 first:border-0 first:pt-0">
                        <p class="font-semibold text-emerald-900">{{ $commission->nom }}</p>
                        @if ($commission->mission)
                            <p class="text-xs text-stone-600">{{ $commission->mission }}</p>
                        @endif
                        <p class="text-xs text-stone-500">Chef : {{ $commission->chef?->prenom }} {{ $commission->chef?->nom }}</p>
                        @if (in_array($commission->id, $dejaMembre, true) || $commission->chef_id === $membre->id)
                            <p class="mt-2 text-sm font-semibold text-emerald-800">✓ Vous en faites partie</p>
                        @elseif (in_array($commission->id, $enAttente, true))
                            <p class="mt-2 text-sm font-semibold text-amber-800">Proposition en attente</p>
                        @else
                            <form method="POST" action="{{ route('volontariat.proposer', $commission) }}" class="mt-2 flex flex-col gap-2 text-sm sm:flex-row">
                                @csrf
                                <input type="text" name="message" maxlength="255" placeholder="Un mot pour le chef (facultatif)"
                                       class="min-w-0 flex-1 rounded-lg border border-stone-300 px-3 py-2.5 text-base">
                                <button type="submit" class="rounded-lg bg-emerald-800 px-4 py-2.5 font-semibold text-white hover:bg-emerald-900">Je me propose</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <div class="mt-6 rounded-xl bg-white p-5 text-sm text-stone-600 shadow-sm ring-1 ring-stone-200">
            Aucun grand événement en préparation pour le moment.
        </div>
    @endforelse

    @if ($propositions->isNotEmpty())
        <div class="mt-5 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            <h2 class="font-semibold text-emerald-900">Mes propositions</h2>
            <ul class="mt-2 divide-y divide-stone-100 text-sm">
                @foreach ($propositions as $proposition)
                    <li class="flex items-center justify-between gap-2 py-2">
                        <span>{{ $proposition->commission->nom }} <span class="text-xs text-stone-500">· {{ $proposition->commission->instance->nom }}</span></span>
                        <span class="flex items-center gap-2 text-xs font-semibold">
                            {{ \App\Models\Volontariat::LIBELLES[$proposition->statut] }}
                            @if ($proposition->statut === \App\Models\Volontariat::PROPOSE)
                                <form method="POST" action="{{ route('volontariat.retirer', $proposition) }}">
                                    @csrf
                                    <button type="submit" class="text-red-700 underline">Retirer</button>
                                </form>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <a href="{{ route('espace') }}" class="mt-6 inline-block text-sm text-emerald-800 underline">Retour à mon compte</a>
@endsection
