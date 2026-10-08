@php
    $membreConnecte = auth()->user()?->membre;
    $annoncesRecentes = $membreConnecte !== null && $membreConnecte->statut === \App\Enums\StatutMembre::Valide
        ? \App\Models\Annonce::query()->enCours()->limit(3)->get()
        : collect();
@endphp

@if ($annoncesRecentes->isNotEmpty())
    <section class="mb-6">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-emerald-900">Annonces</h2>
            <a href="{{ route('annonces.index') }}" class="text-xs font-medium text-emerald-800 underline">Toutes les annonces</a>
        </div>
        <ul class="mt-3 space-y-4">
            @foreach ($annoncesRecentes as $annonce)
                @include('espace.partials.annonce', ['annonce' => $annonce])
            @endforeach
        </ul>
    </section>
@endif
