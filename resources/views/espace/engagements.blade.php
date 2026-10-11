@extends('layouts.public')

@section('titre', 'Mes engagements')

@section('contenu')
    @php $fcfa = fn (int $montant): string => number_format($montant, 0, ',', ' ').' FCFA'; @endphp

    <h1 class="text-2xl font-semibold text-emerald-900">Mes engagements</h1>
    <p class="mt-1 text-sm text-stone-600">Vos participations aux programmes et collectes de la Daara.</p>

    @forelse ($fiches as $fiche)
        @php
            $collecte = $fiche['collecte'];
            $engagement = $fiche['engagement'];
            $actif = (int) old('collecte_id') === $collecte->id;
        @endphp

        <section class="mt-6 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
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
                    <form method="POST" action="{{ route('engagements.fixer', $collecte) }}" class="mt-4 space-y-2">
                        @csrf
                        <input type="hidden" name="collecte_id" value="{{ $collecte->id }}">
                        <label for="montant-{{ $collecte->id }}" class="block text-sm font-semibold text-emerald-900">Fixez votre engagement</label>
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
                        <p class="text-xs text-stone-500">Une fois fixé, votre engagement ne peut être modifié que par le bureau.</p>
                        @if ($actif)
                            @error('montant') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                        @endif
                    </form>
                @else
                    <p class="mt-4 rounded-lg bg-stone-50 p-3 text-sm text-stone-600">Votre engagement sera fixé par le bureau.</p>
                @endif
            @else
                <div class="mt-4">
                    <div class="flex items-baseline justify-between">
                        <p class="text-sm text-stone-600">Versé <span class="font-semibold text-emerald-900">{{ $fcfa($fiche['verse']) }}</span> sur {{ $fcfa($engagement->montant) }}</p>
                        <p class="text-sm font-semibold text-emerald-900">{{ $engagement->taux() }} %</p>
                    </div>
                    <div class="mt-1 h-3 rounded-full bg-stone-200"><div class="h-3 rounded-full bg-emerald-700" style="width: {{ $engagement->taux() }}%"></div></div>
                    @if ($fiche['reste'] === 0)
                        <p class="mt-2 text-sm font-semibold text-emerald-800">✓ Engagement entièrement réglé. Barakallahou fikoum.</p>
                    @else
                        <p class="mt-2 text-sm text-stone-700">Reste à verser : <span class="font-semibold">{{ $fcfa($fiche['reste']) }}</span></p>
                    @endif
                </div>

                @if ($fiche['enCours'])
                    <p class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                        Versement de {{ $fcfa($fiche['enCours']->montant) }} déclaré le {{ $fiche['enCours']->declare_le->format('d/m/Y à H:i') }},
                        en cours de vérification par le Jewrine finance.
                    </p>
                @elseif ($fiche['reste'] > 0 && $collecte->estOuverte())
                    <div class="mt-4 space-y-3 text-sm">
                        <div>
                            <p class="font-semibold text-emerald-900">1. Versez avec Wave</p>
                            @if ($fiche['lienWave'])
                                <a href="{{ $fiche['lienWave'] }}" class="mt-2 inline-block rounded-lg bg-[#1dc8ff] px-5 py-3 font-semibold text-white hover:opacity-90">
                                    Verser {{ $fcfa($fiche['reste']) }} avec Wave
                                </a>
                                <p class="mt-1 text-xs text-stone-500">Vous pouvez aussi verser en plusieurs fois, ou en espèces auprès du Jewrine finance.</p>
                            @else
                                <p class="mt-1 text-stone-600">Le lien Wave n'est pas encore disponible. Vous pouvez verser en espèces auprès du Jewrine finance.</p>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('engagements.versement', $collecte) }}" class="space-y-2">
                            @csrf
                            <input type="hidden" name="collecte_id" value="{{ $collecte->id }}">
                            <p class="font-semibold text-emerald-900">2. Indiquez votre versement</p>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="number" name="montant" min="1" inputmode="numeric" required value="{{ $actif ? old('montant') : $fiche['reste'] }}"
                                       placeholder="Montant versé" title="Montant versé"
                                       class="rounded-lg border border-stone-300 px-3 py-2.5 text-base">
                                <input type="tel" name="numero_wave" value="{{ $actif ? old('numero_wave') : $membre->telephone }}" inputmode="tel"
                                       placeholder="Numéro Wave utilisé" title="Numéro Wave utilisé"
                                       class="rounded-lg border border-stone-300 px-3 py-2.5 text-base">
                            </div>
                            <input type="text" name="reference" value="{{ $actif ? old('reference') : '' }}" required autocomplete="off"
                                   placeholder="Référence Wave, exemple : T_CN535JQDN4VAPI5R"
                                   class="block w-full rounded-lg border border-stone-300 px-3 py-2.5 font-mono text-base">
                            <button type="submit" class="w-full rounded-lg bg-emerald-800 px-5 py-2.5 font-semibold text-white hover:bg-emerald-900">J'ai versé</button>
                            @if ($actif)
                                @error('montant') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                                @error('reference') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                                @error('numero_wave') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                            @endif
                        </form>
                    </div>
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

    <a href="{{ route('espace') }}" class="mt-6 inline-block text-sm text-emerald-800 underline">Retour à mon compte</a>
@endsection
