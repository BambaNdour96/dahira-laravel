@php
    $scan = \App\Services\PresenceService::contexteScan(request()->route('jeton'), auth()->user());
@endphp

@if (session('presence_succes'))
    <div class="mx-auto mt-4 max-w-md rounded-xl bg-emerald-50 p-4 text-center text-sm font-semibold text-emerald-900 ring-1 ring-emerald-200">
        {{ session('presence_succes') }}
    </div>
@endif

@if (session('presence_erreur'))
    <div class="mx-auto mt-4 max-w-md rounded-xl bg-red-50 p-4 text-center text-sm font-semibold text-red-900 ring-1 ring-red-200">
        {{ session('presence_erreur') }}
    </div>
@endif

@if ($scan['membre'] !== null && $scan['evenements'] !== [])
    <div class="mx-auto mt-4 max-w-md space-y-3">
        @foreach ($scan['evenements'] as $ligne)
            @if ($ligne['present'])
                <p class="rounded-xl bg-stone-100 p-4 text-center text-sm text-stone-700">
                    Déjà présent : <strong>{{ $ligne['evenement']->titre }}</strong>
                </p>
            @else
                <form method="POST" action="{{ route('presences.store', [request()->route('jeton'), $ligne['evenement']]) }}">
                    @csrf
                    <button type="submit" class="w-full rounded-xl bg-emerald-800 px-4 py-4 text-lg font-semibold text-white hover:bg-emerald-900">
                        Marquer présent : {{ $ligne['evenement']->titre }}
                    </button>
                </form>
            @endif
        @endforeach
    </div>
@endif
