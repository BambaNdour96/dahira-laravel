@extends('layouts.public')

@section('titre', $proche->prenom.' '.$proche->nom)

@section('contenu')
    @php
        $fcfa = fn (int $montant): string => number_format($montant, 0, ',', ' ').' FCFA';
        $estValide = $proche->statut === \App\Enums\StatutMembre::Valide;
    @endphp

    <div class="flex items-center gap-3">
        @if ($proche->photo_path)
            <img src="{{ route('photos.show', $proche) }}" alt="Photo de {{ $proche->prenom }}" class="h-14 w-14 shrink-0 rounded-full object-cover shadow ring-2 ring-or-400">
        @endif
        <div class="min-w-0">
            <h1 class="truncate text-2xl font-semibold text-emerald-950">{{ $proche->prenom }} {{ $proche->nom }}</h1>
            <p class="text-sm text-stone-600">
                {{ $proche->lien_famille?->getLabel() }}{{ $proche->date_naissance ? ' · '.$proche->date_naissance->age.' ans' : '' }} · {{ $proche->cellule->nom }}
            </p>
            <span class="mt-1 inline-block rounded-full bg-stone-100 px-3 py-0.5 text-xs font-semibold text-stone-800">{{ $proche->statut->getLabel() }}</span>
        </div>
    </div>

    @if (in_array($proche->statut, [\App\Enums\StatutMembre::EnAttente, \App\Enums\StatutMembre::ACorriger], true))
        <div class="mt-5 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200">
            Sa fiche attend la validation de votre Jewrine de cellule.
            @if ($proche->motif_statut)
                <span class="block mt-1">Remarque du Jewrine : {{ $proche->motif_statut }}</span>
            @endif
        </div>
    @endif

    {{-- Frais de carte, après l'acceptation --}}
    @if ($proche->statut === \App\Enums\StatutMembre::Accepte)
        <div class="mt-5 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            <h2 class="font-semibold text-emerald-900">Frais de carte : {{ $fcfa($montantFraisCarte) }}</h2>
            @if ($paiementEnCours)
                <p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                    Paiement déclaré le {{ $paiementEnCours->declare_le->format('d/m/Y à H:i') }}, référence
                    <span class="font-mono">{{ $paiementEnCours->reference }}</span> : en cours de vérification.
                </p>
            @else
                @if ($dernierRefus)
                    <p class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-900">Déclaration précédente refusée : {{ $dernierRefus->motif_refus }}</p>
                @endif
                @if ($lienWave)
                    <a href="{{ $lienWave }}" class="mt-3 inline-block rounded-lg bg-[#1dc8ff] px-5 py-3 text-sm font-semibold text-white hover:opacity-90">
                        Payer {{ $fcfa($montantFraisCarte) }} avec Wave
                    </a>
                @else
                    <p class="mt-2 text-sm text-stone-600">Vous pouvez aussi payer en espèces auprès du représentant finance de la cellule.</p>
                @endif
                <form method="POST" action="{{ route('famille.frais', $proche) }}" class="mt-4 flex flex-col gap-2 text-sm sm:flex-row">
                    @csrf
                    <input type="tel" name="numero_wave" value="{{ old('numero_wave', auth()->user()?->telephone) }}" inputmode="tel"
                           placeholder="Numéro Wave utilisé" class="rounded-lg border border-stone-300 px-3 py-2.5 text-base sm:w-44">
                    <input type="text" name="reference" value="{{ old('reference') }}" required autocomplete="off"
                           placeholder="Exemple : T_CN535JQDN4VAPI5R" class="flex-1 rounded-lg border border-stone-300 px-3 py-2.5 font-mono text-base">
                    <button type="submit" class="rounded-lg bg-emerald-800 px-5 py-2.5 font-semibold text-white hover:bg-emerald-900">J'ai payé</button>
                </form>
                @error('reference')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
                @error('numero_wave')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            @endif
        </div>
    @endif

    @if ($estValide)
        <div class="mt-5 grid grid-cols-2 gap-3">
            <a href="{{ route('famille.carte', $proche) }}" class="rounded-xl bg-emerald-900 p-4 text-sm font-semibold text-white hover:bg-emerald-950">
                <span class="block text-2xl" aria-hidden="true">🪪</span> Sa carte
                <span class="block text-xs font-normal text-emerald-100">{{ $proche->matricule }}</span>
            </a>
            <a href="{{ route('famille.cotisations', $proche) }}" class="rounded-xl p-4 text-sm font-semibold ring-1 {{ $retard > 0 ? 'bg-red-50 text-red-900 ring-red-200' : 'bg-white text-emerald-950 ring-stone-200' }} hover:bg-stone-100">
                <span class="block text-2xl" aria-hidden="true">💳</span> Ses cotisations
                <span class="block text-xs font-normal">{{ $retard > 0 ? $retard.' mois de retard' : 'À jour' }}</span>
            </a>
            <a href="{{ route('famille.engagements', $proche) }}" class="col-span-2 rounded-xl bg-white p-4 text-sm font-semibold text-emerald-950 ring-1 ring-or-400 hover:bg-stone-100">
                <span class="mr-1" aria-hidden="true">🤲</span> Ses Sass et Jàyante
            </a>
        </div>
    @endif

    @if ($recus->isNotEmpty())
        <div class="mt-5 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            <h2 class="font-semibold text-emerald-900">Ses reçus</h2>
            <ul class="mt-3 divide-y divide-stone-100 text-sm">
                @foreach ($recus as $paiement)
                    <li class="flex items-center justify-between gap-2 py-2">
                        <span>{{ $paiement->traite_le?->format('d/m/Y') }} · {{ $paiement->libelleObjet() }}</span>
                        <a href="{{ route('recus.show', $paiement) }}" target="_blank" class="shrink-0 font-medium text-emerald-800 underline">{{ $fcfa($paiement->montant) }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($estValide && $competences !== [])
        <form method="POST" action="{{ route('famille.competences', $proche) }}" class="mt-5 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            @csrf
            <h2 class="font-semibold text-emerald-900">Ses savoir-faire</h2>
            <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                @foreach ($competences as $id => $nom)
                    <label class="flex items-center gap-2 rounded-lg border border-stone-200 px-3 py-2 has-[:checked]:border-emerald-700 has-[:checked]:bg-emerald-50">
                        <input type="checkbox" name="competences[]" value="{{ $id }}" class="rounded" @checked(in_array($id, $sesCompetences, true))>
                        {{ $nom }}
                    </label>
                @endforeach
            </div>
            <button type="submit" class="mt-3 w-full rounded-lg bg-emerald-800 px-4 py-2.5 font-semibold text-white hover:bg-emerald-900">Enregistrer</button>
        </form>
    @endif

    {{-- Son propre compte : proposé à 18 ans, jamais imposé --}}
    @if ($peutDonnerSonCompte)
        <div class="mt-5 rounded-xl bg-or-100 p-5 ring-1 ring-or-400">
            <h2 class="font-semibold text-emerald-950">Lui donner son propre compte</h2>
            <p class="mt-1 text-sm text-emerald-900">
                {{ $proche->prenom }} se connectera avec son propre numéro et gardera sa fiche, sa carte et tout son historique.
                Vous ne gérerez plus sa fiche. Ce n'est pas obligatoire.
            </p>
            <form method="POST" action="{{ route('famille.compte', $proche) }}" class="mt-3 flex flex-col gap-2 text-sm sm:flex-row">
                @csrf
                <input type="tel" name="telephone" value="{{ old('telephone', $proche->telephone) }}" inputmode="tel" required
                       placeholder="Son numéro : 77 123 45 67" class="flex-1 rounded-lg border border-stone-300 px-3 py-2.5 text-base">
                <button type="submit" class="rounded-lg bg-emerald-800 px-5 py-2.5 font-semibold text-white hover:bg-emerald-900">Créer son compte</button>
            </form>
            @error('telephone')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>
    @endif

    <a href="{{ route('famille.index') }}" class="mt-6 inline-block text-sm text-emerald-800 underline">Retour à ma famille</a>
@endsection
