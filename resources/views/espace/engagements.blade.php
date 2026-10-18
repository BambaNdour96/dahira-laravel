@extends('layouts.public')

@section('titre', $proche ? 'Sass et Jàyante de '.$proche->prenom : 'Mes Sass et Jàyante')

@section('contenu')
    @php $fcfa = fn (int $montant): string => number_format($montant, 0, ',', ' ').' FCFA'; @endphp

    <h1 class="text-2xl font-semibold text-emerald-900">{{ $proche ? 'Sass et Jàyante de '.$proche->prenom : 'Mes Sass et Jàyante' }}</h1>
    <p class="mt-1 text-sm text-stone-600">{{ $proche ? 'Ses participations' : 'Vos participations' }} aux programmes et collectes de la Daara.</p>

    @forelse ($fiches as $fiche)
        @php
            $collecte = $fiche['collecte'];
            $engagement = $fiche['engagement'];
            $actif = (int) old('collecte_id') === $collecte->id;
        @endphp

        <section id="collecte-{{ $collecte->id }}" class="mt-6 scroll-mt-24 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">{{ $collecte->programme->getLabel() }}</p>
                    <h2 class="text-lg font-semibold text-emerald-950">{{ $collecte->titre }}</h2>
                    @if ($collecte->date_limite)
                        <p class="text-sm {{ $collecte->estEnRetard() && $fiche['reste'] > 0 ? 'font-semibold text-red-800' : 'text-stone-500' }}">
                            Date limite : {{ $collecte->date_limite->translatedFormat('j F Y') }}
                            @if ($collecte->estEnRetard() && $fiche['reste'] > 0) · en retard @endif
                        </p>
                    @endif
                </div>
                @if ($collecte->obligatoire)
                    <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-900 ring-1 ring-emerald-200">Obligatoire</span>
                @endif
            </div>

            @if ($fiche['jourJ'])
                <p class="mt-3 rounded-lg bg-sky-50 p-3 text-sm text-sky-900 ring-1 ring-sky-200">
                    📍 <span class="font-semibold">Jour J :</span>
                    {{ $fiche['jourJ']->debut->translatedFormat('j F Y à H:i') }} · {{ $fiche['jourJ']->lieu }}
                    @if ($fiche['present'])
                        <span class="ml-1 font-semibold text-emerald-800">· ✓ Vous étiez présent</span>
                    @elseif ($fiche['jourJ']->debut->isFuture())
                        <span class="block text-xs text-sky-800">Présentez votre carte de membre à l'entrée.</span>
                    @endif
                </p>
            @endif

            @if ($collecte->description)
                <p class="mt-3 text-sm text-stone-700">{{ $collecte->description }}</p>
            @endif

            @if ($fiche['jauge'] !== null)
                <div class="mt-4 rounded-lg bg-stone-50 p-3 ring-1 ring-stone-200">
                    <div class="flex justify-between text-xs font-medium text-stone-600">
                        <span>Avancement de la collecte</span><span>{{ $fiche['jauge'] }} %</span>
                    </div>
                    <div class="mt-1 h-2 rounded-full bg-stone-200"><div class="h-2 rounded-full bg-or-500" style="width: {{ $fiche['jauge'] }}%"></div></div>
                </div>
            @endif

            @if ($engagement === null)
                @if ($fiche['peutFixer'])
                    <form method="POST" action="{{ $proche ? route('famille.engagements.fixer', [$proche, $collecte]) : route('engagements.fixer', $collecte) }}" class="mt-4 space-y-2">
                        @csrf
                        <input type="hidden" name="collecte_id" value="{{ $collecte->id }}">
                        <label for="montant-{{ $collecte->id }}" class="block text-sm font-semibold text-emerald-900">{{ $proche ? 'Fixez son Jàyante' : 'Fixez votre Jàyante' }}</label>
                        <p class="text-xs text-stone-500">
                            {{ $collecte->mode->getDescription() }}
                            @if ($fiche['minimum'] > 1) Minimum : {{ $fcfa($fiche['minimum']) }}. @endif
                        </p>
                        <div class="flex gap-2">
                            <input id="montant-{{ $collecte->id }}" type="number" name="montant" min="{{ $fiche['minimum'] }}" step="500" inputmode="numeric" required
                                   value="{{ $actif ? old('montant') : '' }}" placeholder="Montant en FCFA"
                                   class="min-w-0 flex-1 rounded-lg border border-stone-300 px-3 py-2.5 text-base">
                            <button type="submit" class="rounded-lg bg-emerald-800 px-4 py-2.5 font-semibold text-white hover:bg-emerald-900">M'engager</button>
                        </div>
                        <p class="text-xs text-stone-500">Une fois fixé, votre Jàyante ne peut être modifié que par le bureau.</p>
                        @if ($actif)
                            @error('montant') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                        @endif
                    </form>
                @else
                    <p class="mt-4 rounded-lg bg-stone-50 p-3 text-sm text-stone-600">Votre Sass sera fixé par le bureau.</p>
                @endif
            @else
                @php
                    $pctVerse = $engagement->taux();
                    $pctAttente = $engagement->montant > 0
                        ? max(0, min(100 - $pctVerse, (int) round($fiche['enVerification'] / $engagement->montant * 100)))
                        : 0;
                @endphp
                <div class="mt-4">
                    <div class="flex items-baseline justify-between">
                        <p class="text-sm text-stone-600">Versé <span class="font-semibold text-emerald-900">{{ $fcfa($fiche['verse']) }}</span> sur {{ $fcfa($engagement->montant) }} · {{ $engagement->nature() }}</p>
                        <p class="text-sm font-semibold text-emerald-900">{{ $pctVerse }} %</p>
                    </div>
                    <div class="mt-1 flex h-3 overflow-hidden rounded-full bg-stone-200" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pctVerse }}">
                        <div class="h-3 bg-emerald-700" style="width: {{ $pctVerse }}%"></div>
                        <div class="h-3 bg-amber-400" style="width: {{ $pctAttente }}%"></div>
                    </div>
                    @if ($fiche['enVerification'] > 0)
                        <p class="mt-2 text-sm text-amber-800">
                            <span class="inline-block h-2.5 w-2.5 rounded-full bg-amber-400 align-middle"></span>
                            {{ $fcfa($fiche['enVerification']) }} en vérification par le Jewrine finance : votre jauge avancera dès sa confirmation.
                        </p>
                    @endif
                    @if ($fiche['reste'] === 0)
                        <p class="mt-2 text-sm font-semibold text-emerald-800">✓ {{ $engagement->nature() }} entièrement réglé. Barakallahou fikoum.</p>
                    @else
                        <p class="mt-2 text-sm text-stone-700">Reste à verser : <span class="font-semibold">{{ $fcfa($fiche['reste']) }}</span></p>
                    @endif
                </div>

                @if ($fiche['disponible'] > 0 && $collecte->estOuverte())
                    @php
                        $montantPropose = $actif && old('montant') ? (int) old('montant') : $fiche['disponible'];
                        $rapides = array_values(array_filter([2000, 5000, 10000], fn (int $montant): bool => $montant < $fiche['disponible']));
                    @endphp
                    <form method="POST" action="{{ $proche ? route('famille.engagements.versement', [$proche, $collecte]) : route('engagements.versement', $collecte) }}" class="mt-4 space-y-4 text-sm"
                          data-versement data-lien="{{ $fiche['lienWave'] }}" data-max="{{ $fiche['disponible'] }}">
                        @csrf
                        <input type="hidden" name="collecte_id" value="{{ $collecte->id }}">

                        <div>
                            <p class="font-semibold text-emerald-900">1. Combien voulez-vous verser aujourd'hui ?</p>
                            <p class="text-xs text-stone-500">Vous pouvez verser petit à petit, jusqu'à {{ $fcfa($fiche['disponible']) }}.</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($rapides as $rapide)
                                    <button type="button" data-montant="{{ $rapide }}"
                                            class="rounded-full bg-white px-3 py-1.5 font-semibold text-emerald-900 ring-1 ring-emerald-300 hover:bg-emerald-50">{{ $fcfa($rapide) }}</button>
                                @endforeach
                                <button type="button" data-montant="{{ $fiche['disponible'] }}"
                                        class="rounded-full bg-white px-3 py-1.5 font-semibold text-emerald-900 ring-1 ring-emerald-300 hover:bg-emerald-50">Tout le reste ({{ $fcfa($fiche['disponible']) }})</button>
                            </div>
                            <label for="montant-{{ $collecte->id }}" class="mt-3 block text-xs font-medium text-stone-600">Ou écrivez le montant de votre choix</label>
                            <div class="mt-1 flex items-center gap-2">
                                <input id="montant-{{ $collecte->id }}" type="number" name="montant" min="100" max="{{ $fiche['disponible'] }}" step="50" inputmode="numeric" required
                                       value="{{ $montantPropose }}"
                                       class="w-40 rounded-lg border border-stone-300 px-3 py-2.5 text-base font-semibold">
                                <span class="text-stone-600">FCFA</span>
                            </div>
                        </div>

                        <div>
                            <p class="font-semibold text-emerald-900">2. Payez avec Wave</p>
                            @if ($fiche['lienWave'])
                                <a href="{{ $fiche['lienWave'] }}" data-bouton-wave
                                   class="mt-2 inline-block rounded-lg bg-[#1dc8ff] px-5 py-3 font-semibold text-white hover:opacity-90">
                                    Payer <span data-montant-affiche>{{ $fcfa($montantPropose) }}</span> avec Wave
                                </a>
                            @else
                                <p class="mt-1 text-stone-600">Le lien Wave n'est pas encore disponible.</p>
                            @endif
                            <p class="mt-1 text-xs text-stone-500">Vous pouvez aussi payer en espèces auprès du Jewrine finance.</p>
                        </div>

                        <div class="space-y-2">
                            <p class="font-semibold text-emerald-900">3. Indiquez votre paiement</p>
                            <input type="text" name="reference" value="{{ $actif ? old('reference') : '' }}" required autocomplete="off"
                                   placeholder="Référence Wave, exemple : T_CN535JQDN4VAPI5R"
                                   class="block w-full rounded-lg border border-stone-300 px-3 py-2.5 font-mono text-base">
                            <input type="tel" name="numero_wave" value="{{ $actif ? old('numero_wave') : ($proche ? auth()->user()?->telephone : $membre->telephone) }}" inputmode="tel"
                                   placeholder="Numéro Wave utilisé" title="Numéro Wave utilisé"
                                   class="block w-full rounded-lg border border-stone-300 px-3 py-2.5 text-base">
                            <button type="submit" class="w-full rounded-lg bg-emerald-800 px-5 py-3 font-semibold text-white hover:bg-emerald-900">
                                J'ai payé <span data-montant-affiche>{{ $fcfa($montantPropose) }}</span>
                            </button>
                            @if ($actif)
                                @error('montant') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                                @error('reference') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                                @error('numero_wave') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                            @endif
                        </div>
                    </form>
                @elseif ($fiche['reste'] > 0 && $fiche['disponible'] === 0)
                    <p class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Vos versements en vérification couvrent tout votre {{ $engagement->nature() }}. Merci !</p>
                @endif

                @if ($fiche['versements']->isNotEmpty())
                    <details class="mt-4 text-sm">
                        <summary class="cursor-pointer font-medium text-emerald-800">Mes versements ({{ $fiche['versements']->count() }})</summary>
                        <ul class="mt-2 divide-y divide-stone-100">
                            @foreach ($fiche['versements'] as $versement)
                                <li class="flex items-center justify-between py-2">
                                    <span>{{ $versement->declare_le->format('d/m/Y') }} · {{ $fcfa($versement->montant) }} · {{ $versement->moyen->getLabel() }}</span>
                                    <span class="flex items-center gap-2">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $versement->statut === \App\Enums\StatutPaiement::Confirme ? 'bg-emerald-100 text-emerald-900' : ($versement->statut === \App\Enums\StatutPaiement::Refuse ? 'bg-red-100 text-red-900' : 'bg-amber-100 text-amber-900') }}">{{ $versement->statut->getLabel() }}</span>
                                        @if ($versement->numero_recu)
                                            <a href="{{ route('recus.show', $versement) }}" class="text-xs font-medium text-emerald-800 underline">Reçu</a>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            @endif
        </section>
    @empty
        <div class="mt-6 rounded-xl bg-white p-6 text-center text-sm text-stone-600 ring-1 ring-stone-200">
            Aucune collecte en cours pour le moment.
        </div>
    @endforelse

    @if ($proche)
        <a href="{{ route('famille.show', $proche) }}" class="mt-6 inline-block text-sm text-emerald-800 underline">Retour à la fiche de {{ $proche->prenom }}</a>
    @else
        <a href="{{ route('espace') }}" class="mt-6 inline-block text-sm text-emerald-800 underline">Retour à mon compte</a>
    @endif
@endsection
