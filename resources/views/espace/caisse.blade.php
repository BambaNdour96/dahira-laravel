@extends('layouts.public')

@section('titre', 'Ma caisse')

@section('contenu')
    <h1 class="text-2xl font-semibold text-emerald-900">Ma caisse</h1>
    <p class="text-sm text-stone-600">
        Représentant du Jewrine finance :
        @foreach ($representations as $representation)
            <span class="font-semibold">{{ \App\Models\RepresentantFinance::PERIMETRES[$representation->perimetre] }} de la cellule {{ $representation->cellule->nom }}</span>{{ $loop->last ? '' : ', ' }}
        @endforeach
    </p>

    @if (session('caisse_succes'))
        <p class="mt-3 rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-900">{{ session('caisse_succes') }}</p>
    @endif
    @if (session('caisse_erreur'))
        <p class="mt-3 rounded-lg bg-red-50 p-3 text-sm font-semibold text-red-900">{{ session('caisse_erreur') }}</p>
    @endif

    <div class="mt-4 grid grid-cols-2 gap-3">
        <div class="rounded-xl bg-emerald-800 p-4 text-white">
            <p class="text-xs uppercase tracking-wide text-emerald-100">En caisse</p>
            <p class="text-2xl font-bold">{{ number_format($enCaisse, 0, ',', ' ') }} FCFA</p>
            <p class="text-xs text-emerald-100">{{ $operations->count() }} encaissement(s) à remettre</p>
        </div>
        <div class="rounded-xl bg-white p-4 ring-1 ring-stone-200">
            <p class="text-xs uppercase tracking-wide text-stone-500">À valider</p>
            <p class="text-2xl font-bold text-amber-700">{{ $aValider->count() }}</p>
            <p class="text-xs text-stone-500">paiement(s) Wave déclarés</p>
        </div>
    </div>

    <section class="mt-6">
        <h2 class="text-lg font-semibold text-emerald-900">Paiements Wave à valider</h2>
        <p class="text-xs text-stone-500">Vérifiez la référence et le montant sur votre relevé Wave avant de valider.</p>
        @forelse ($aValider as $paiement)
            <div class="mt-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-amber-200">
                <p class="font-semibold text-emerald-950">{{ $paiement->membre->prenom }} {{ $paiement->membre->nom }}</p>
                <p class="text-sm text-stone-700">
                    {{ $paiement->objet === 'cotisation' ? 'Cotisation, '.$paiement->nombre_mois.' mois' : ($paiement->collecte->titre ?? 'Programme') }}
                    · <span class="font-semibold">{{ number_format($paiement->montant, 0, ',', ' ') }} FCFA</span>
                </p>
                <p class="font-mono text-xs text-stone-500">{{ $paiement->reference }} · déclaré le {{ $paiement->declare_le->format('d/m à H:i') }}</p>
                <div class="mt-3 flex gap-2">
                    <form method="POST" action="{{ route('caisse.valider', $paiement) }}">
                        @csrf
                        <button type="submit" class="rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white">Valider</button>
                    </form>
                    <details class="flex-1">
                        <summary class="cursor-pointer rounded-lg px-4 py-2 text-sm font-semibold text-red-800 ring-1 ring-red-200">Refuser…</summary>
                        <form method="POST" action="{{ route('caisse.refuser', $paiement) }}" class="mt-2 flex gap-2">
                            @csrf
                            <input type="text" name="motif" required maxlength="255" placeholder="Motif : référence introuvable…"
                                   class="min-w-0 flex-1 rounded-lg border border-stone-300 px-3 py-2 text-sm">
                            <button type="submit" class="rounded-lg bg-red-700 px-3 py-2 text-sm font-semibold text-white">Refuser</button>
                        </form>
                    </details>
                </div>
            </div>
        @empty
            <p class="mt-3 rounded-xl bg-white p-4 text-sm text-stone-600 ring-1 ring-stone-200">Aucun paiement en attente.</p>
        @endforelse
    </section>

    <section class="mt-6">
        <h2 class="text-lg font-semibold text-emerald-900">Encaisser des espèces</h2>
        <p class="text-xs text-stone-500">Choisissez le membre : sa cotisation, ses Sass et ses Jàyante s'affichent.</p>
        <input type="search" data-chercher-membre placeholder="Chercher un nom ou un matricule…"
               class="mt-3 block w-full rounded-lg border border-stone-300 px-3 py-2.5 text-base">
        <ul class="mt-2 divide-y divide-stone-100 rounded-xl bg-white ring-1 ring-stone-200">
            @foreach ($membres as $membre)
                <li data-membre="{{ mb_strtolower($membre->prenom.' '.$membre->nom.' '.$membre->matricule) }}">
                    <a href="{{ route('caisse.membre', $membre) }}" class="flex items-center justify-between px-4 py-3 hover:bg-stone-50">
                        <span class="font-medium text-emerald-950">{{ $membre->prenom }} {{ $membre->nom }}</span>
                        <span class="text-xs text-stone-500">{{ $membre->matricule }} ›</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="mt-6">
        <h2 class="text-lg font-semibold text-emerald-900">Espèces à remettre au Jewrine finance</h2>
        @forelse ($operations as $operation)
            <div class="mt-2 flex items-center justify-between rounded-lg bg-white px-4 py-3 text-sm ring-1 ring-stone-200">
                <span>{{ $operation->traite_le?->format('d/m') }} · {{ $operation->membre->prenom }} {{ $operation->membre->nom }}</span>
                <span class="font-semibold">{{ number_format($operation->montant, 0, ',', ' ') }} FCFA</span>
            </div>
        @empty
            <p class="mt-2 text-sm text-stone-600">Votre caisse est vide.</p>
        @endforelse
        <p class="mt-2 text-xs text-stone-500">À la remise, le Jewrine finance enregistre la réception : votre caisse revient à zéro, et un bordereau signé de vos deux noms est produit.</p>
    </section>

    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        document.querySelector('[data-chercher-membre]')?.addEventListener('input', (evenement) => {
            const texte = evenement.target.value.trim().toLowerCase();
            document.querySelectorAll('[data-membre]').forEach((ligne) => {
                ligne.classList.toggle('hidden', texte !== '' && !ligne.dataset.membre.includes(texte));
            });
        });
    </script>
@endsection
