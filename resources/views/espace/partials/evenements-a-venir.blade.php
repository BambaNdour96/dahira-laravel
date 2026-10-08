@php
    $membreConnecte = auth()->user()?->membre;
    $prochainsEvenements = $membreConnecte !== null && $membreConnecte->statut === \App\Enums\StatutMembre::Valide
        ? \App\Models\Evenement::query()
            ->where(fn ($cible) => $cible->whereNull('cellule_id')->orWhere('cellule_id', $membreConnecte->cellule_id))
            ->where('debut', '>=', now()->startOfDay())
            ->orderBy('debut')
            ->limit(2)
            ->get()
        : collect();
@endphp

@if ($prochainsEvenements->isNotEmpty())
    <section class="mb-6">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-emerald-900">Prochains événements</h2>
            <a href="{{ route('evenements.index') }}" class="text-xs font-medium text-emerald-800 underline">Tous les événements</a>
        </div>
        <ul class="mt-3 space-y-4">
            @foreach ($prochainsEvenements as $evenement)
                @include('espace.partials.evenement', ['evenement' => $evenement])
            @endforeach
        </ul>
    </section>
@endif
