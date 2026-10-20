@extends('layouts.public')

@section('titre', 'Renouvellement')

@section('contenu')
    @php
        $fcfa = fn (int $montant): string => number_format($montant, 0, ',', ' ').' FCFA';
        $pour = $proche ? 'de '.$proche->prenom : '';
        $routeVerifier = $proche ? route('famille.renouvellement.verifier', $proche) : route('renouvellement.verifier');
        $routeSignaler = $proche ? route('famille.renouvellement.signaler', $proche) : route('renouvellement.signaler');
        $routePayer = $proche ? route('famille.renouvellement.payer', $proche) : route('renouvellement.payer');
        $aPayer = $renouvellement !== null && in_array($renouvellement->statut, \App\Models\Renouvellement::A_PAYER, true);
    @endphp

    <h1 class="text-2xl font-semibold text-emerald-900">Renouvellement {{ $pour }}</h1>

    @error('renouvellement')
        <p class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-900">{{ $message }}</p>
    @enderror

    @if ($renouvellement === null)
        <p class="mt-4 rounded-xl bg-white p-5 text-sm text-stone-600 ring-1 ring-stone-200">Aucune campagne de renouvellement en cours.</p>
    @else
        @php
            $campagne = $renouvellement->campagne;
        @endphp
        <p class="mt-1 text-sm text-stone-600">
            Adhésion {{ $campagne->exercice->libelle }} · frais de renouvellement : <strong>{{ $fcfa($montant) }}</strong>
            · date limite : {{ $campagne->date_limite->translatedFormat('j F Y') }}
        </p>
        <p class="mt-3 inline-block rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-800">{{ \App\Models\Renouvellement::LIBELLES[$renouvellement->statut] }}</p>

        @if ($renouvellement->statut === \App\Models\Renouvellement::NON_RENOUVELE)
            <p class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-900">
                La date limite est passée : la carte n'est plus valide. Le renouvellement reste possible ; la carte revient dès la
                validation du paiement, et les cotisations des mois écoulés restent dues.
            </p>
        @endif

        @if ($renouvellement->estFait())
            <p class="mt-4 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900 ring-1 ring-emerald-200">
                ✓ Adhésion {{ $campagne->exercice->libelle }} renouvelée. Barakallahou fikoum.
                <a href="{{ $proche ? route('famille.carte', $proche) : route('carte') }}" class="ml-1 font-semibold underline">Voir la carte</a>
            </p>
        @elseif ($renouvellement->statut === \App\Models\Renouvellement::A_REVALIDER)
            <p class="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200">
                Payé. La fiche a changé : le Jewrine de cellule la revalide, puis la nouvelle carte sera émise.
            </p>
        @elseif ($aPayer)
            {{-- Étape 1 : la fiche --}}
            <section class="mt-5 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                <h2 class="font-semibold text-emerald-900">1. Vérifier la fiche</h2>
                <div class="mt-3 flex items-center gap-3">
                    @if ($membre->photo_path)
                        <img src="{{ route('photos.show', $membre) }}" alt="" class="h-16 w-16 rounded-lg object-cover ring-2 ring-emerald-700">
                    @endif
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                        <dt class="text-stone-500">Prénom et nom</dt><dd class="font-semibold">{{ $membre->prenom }} {{ $membre->nom }}</dd>
                        <dt class="text-stone-500">Sexe</dt><dd>{{ $membre->sexe === \App\Enums\Sexe::Homme ? 'Goor Yalla' : 'Sokhna' }}</dd>
                        <dt class="text-stone-500">Né(e) le</dt><dd>{{ $membre->date_naissance?->format('d/m/Y') ?? '—' }}</dd>
                        <dt class="text-stone-500">Cellule</dt><dd>{{ $membre->cellule->nom }}</dd>
                    </dl>
                </div>
                @if ($renouvellement->fiche_verifiee_le)
                    <p class="mt-3 text-sm font-semibold text-emerald-800">✓ Fiche vérifiée le {{ $renouvellement->fiche_verifiee_le->format('d/m/Y') }}</p>
                @else
                    <form method="POST" action="{{ $routeVerifier }}" class="mt-4">
                        @csrf
                        <button type="submit" class="w-full rounded-lg bg-emerald-800 px-5 py-3 font-semibold text-white hover:bg-emerald-900">Tout est exact</button>
                    </form>
                    <details class="mt-3 text-sm">
                        <summary class="cursor-pointer font-medium text-emerald-800">Une information est fausse ?</summary>
                        <p class="mt-2 text-xs text-stone-500">
                            La photo se change dans « Mon profil ». Pour le reste, votre Jewrine de cellule corrige la fiche ; la nouvelle carte attendra sa revalidation.
                        </p>
                        <form method="POST" action="{{ $routeSignaler }}" class="mt-2 flex flex-col gap-2 sm:flex-row">
                            @csrf
                            <input type="text" name="message" required maxlength="300" placeholder="Ce qu'il faut corriger"
                                   class="min-w-0 flex-1 rounded-lg border border-stone-300 px-3 py-2.5 text-base">
                            <button type="submit" class="rounded-lg bg-white px-4 py-2.5 font-semibold text-emerald-900 ring-1 ring-emerald-300">Prévenir le Jewrine</button>
                        </form>
                    </details>
                @endif
            </section>

            {{-- Étape 2 : le paiement --}}
            <section class="mt-5 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200 {{ $renouvellement->fiche_verifiee_le ? '' : 'opacity-60' }}">
                <h2 class="font-semibold text-emerald-900">2. Régler {{ $fcfa($montant) }}</h2>
                @if ($enCours)
                    <p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                        Paiement déclaré le {{ $enCours->declare_le->format('d/m/Y à H:i') }}, référence <span class="font-mono">{{ $enCours->reference }}</span> : en cours de vérification.
                    </p>
                @elseif ($renouvellement->fiche_verifiee_le)
                    @if ($lienWave)
                        <a href="{{ $lienWave }}" class="mt-3 inline-block rounded-lg bg-[#1dc8ff] px-5 py-3 text-sm font-semibold text-white hover:opacity-90">Payer {{ $fcfa($montant) }} avec Wave</a>
                    @endif
                    <p class="mt-2 text-xs text-stone-500">Vous pouvez aussi payer en espèces auprès du représentant finance de la cellule.</p>
                    <form method="POST" action="{{ $routePayer }}" class="mt-3 flex flex-col gap-2 text-sm sm:flex-row">
                        @csrf
                        <input type="tel" name="numero_wave" value="{{ old('numero_wave', auth()->user()?->telephone) }}" inputmode="tel"
                               placeholder="Numéro Wave utilisé" class="rounded-lg border border-stone-300 px-3 py-2.5 text-base sm:w-44">
                        @include('partials.moyen-paiement')
                        <input type="text" name="reference" value="{{ old('reference') }}" required autocomplete="off"
                               placeholder="Exemple : T_CN535JQDN4VAPI5R" class="flex-1 rounded-lg border border-stone-300 px-3 py-2.5 font-mono text-base">
                        <button type="submit" class="rounded-lg bg-emerald-800 px-5 py-2.5 font-semibold text-white hover:bg-emerald-900">J'ai payé</button>
                    </form>
                    @error('reference')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                @else
                    <p class="mt-2 text-sm text-stone-600">Vérifiez d'abord la fiche.</p>
                @endif
            </section>
        @endif
    @endif

    @if ($proche)
        <a href="{{ route('famille.show', $proche) }}" class="mt-6 inline-block text-sm text-emerald-800 underline">Retour à la fiche de {{ $proche->prenom }}</a>
    @else
        <a href="{{ route('espace') }}" class="mt-6 inline-block text-sm text-emerald-800 underline">Retour à mon compte</a>
    @endif
@endsection
