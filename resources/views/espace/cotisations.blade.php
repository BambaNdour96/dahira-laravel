@extends('layouts.public')

@section('titre', 'Mes cotisations')

@section('contenu')
    @php
        $fcfa = fn (int $montant): string => number_format($montant, 0, ',', ' ').' FCFA';
        $couleurs = [
            'regle' => 'bg-emerald-700 text-white',
            'exonere' => 'bg-sky-600 text-white',
            'retard' => 'bg-red-600 text-white',
            'courant' => 'bg-or-500 text-emerald-950',
            'avenir' => 'bg-stone-100 text-stone-500',
            'avant' => 'bg-stone-50 text-stone-300',
        ];
        $choixParDefaut = (int) old('nombre_mois', in_array(count($retard), $choix, true) ? count($retard) : 1);
        $lienParDefaut = $liensWave[$choixParDefaut] ?? null;
    @endphp

    <h1 class="text-2xl font-semibold text-emerald-900">Mes cotisations</h1>
    <p class="mt-1 text-sm text-stone-600">Cotisation mensuelle : <strong>{{ $fcfa($mensuel) }}</strong>.</p>

    {{-- État --}}
    @if (count($retard) > 0)
        <div class="mt-6 rounded-xl bg-red-50 p-5 ring-1 ring-red-200">
            <p class="font-semibold text-red-900">En retard de {{ count($retard) }} mois</p>
            <p class="mt-1 text-sm text-red-800">
                Mois impayés :
                {{ collect($retard)->map(fn ($mois) => $mois->translatedFormat('F Y'))->join(', ') }}.
            </p>
        </div>
    @else
        <div class="mt-6 rounded-xl bg-emerald-50 p-5 ring-1 ring-emerald-200">
            <p class="font-semibold text-emerald-900">Cotisation à jour</p>
            <p class="mt-1 text-sm text-emerald-800">
                @if ($regleJusqua)
                    Réglée jusqu'à {{ $regleJusqua->translatedFormat('F Y') }} inclus.
                @elseif ($debut)
                    Vos cotisations commencent en {{ $debut->translatedFormat('F Y') }}.
                @endif
            </p>
        </div>
    @endif

    {{-- Calendrier de l'année --}}
    <div class="mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
        <h2 class="font-semibold text-emerald-900">Année {{ now()->year }}</h2>
        <div class="mt-3 grid grid-cols-4 gap-2 text-center text-xs font-semibold sm:grid-cols-6">
            @foreach ($calendrier as $case)
                <div class="rounded-lg px-1 py-2 {{ $couleurs[$case['etat']] }}">
                    {{ ucfirst($case['mois']->translatedFormat('M')) }}
                </div>
            @endforeach
        </div>
        <div class="mt-3 flex flex-wrap gap-3 text-xs text-stone-600">
            <span><span class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-700"></span> Réglé</span>
            <span><span class="inline-block h-2.5 w-2.5 rounded-full bg-sky-600"></span> Dispensé</span>
            <span><span class="inline-block h-2.5 w-2.5 rounded-full bg-red-600"></span> En retard</span>
            <span><span class="inline-block h-2.5 w-2.5 rounded-full bg-or-500"></span> Mois en cours</span>
            <span><span class="inline-block h-2.5 w-2.5 rounded-full bg-stone-200"></span> À venir</span>
        </div>
    </div>

    {{-- Payer --}}
    <div class="mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
        <h2 class="font-semibold text-emerald-900">Payer mes cotisations</h2>

        @if ($enCours)
            <p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                Paiement de {{ $fcfa($enCours->montant) }} ({{ $enCours->nombre_mois }} mois) déclaré le
                {{ $enCours->declare_le->format('d/m/Y à H:i') }}, référence
                <span class="font-mono">{{ $enCours->reference }}</span>.
                Il est en cours de vérification par le Jewrine finance.
            </p>
        @else
            @if ($dernierRefus)
                <p class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-900">
                    Votre déclaration précédente a été refusée : {{ $dernierRefus->motif_refus }}
                </p>
            @endif

            <form method="POST" action="{{ route('cotisations.declarer') }}" class="mt-4 space-y-4 text-sm">
                @csrf

                <div>
                    <p class="font-semibold text-emerald-900">1. Choisissez le nombre de mois</p>
                    <div class="mt-2 grid grid-cols-3 gap-2">
                        @foreach ($choix as $nombre)
                            <label class="flex cursor-pointer flex-col items-center rounded-lg border border-stone-300 px-2 py-3 has-[:checked]:border-emerald-700 has-[:checked]:bg-emerald-50">
                                <input type="radio" name="nombre_mois" value="{{ $nombre }}" class="sr-only"
                                       data-lien-wave="{{ $liensWave[$nombre] ?? '' }}"
                                       data-montant="{{ $fcfa($nombre * $mensuel) }}"
                                       @checked($choixParDefaut === $nombre) required>
                                <span class="font-semibold">{{ $nombre }} mois</span>
                                <span class="text-xs text-stone-500">{{ $fcfa($nombre * $mensuel) }}</span>
                            </label>
                        @endforeach
                    </div>
                    @if (count($retard) > 0)
                        <p class="mt-1 text-xs text-stone-500">Votre paiement couvre d'abord vos mois de retard.</p>
                    @endif
                </div>

                <div>
                    <p class="font-semibold text-emerald-900">2. Payez avec Wave</p>
                    <a id="bouton-wave" href="{{ $lienParDefaut ?? '#' }}" target="_blank" rel="noopener"
                       class="mt-2 inline-block rounded-lg bg-[#1dc8ff] px-5 py-3 font-semibold text-white hover:opacity-90 {{ $lienParDefaut ? '' : 'hidden' }}">
                        Payer <span id="montant-wave">{{ $fcfa($choixParDefaut * $mensuel) }}</span> avec Wave
                    </a>
                    <p id="lien-absent" class="mt-1 text-stone-600 {{ $lienParDefaut ? 'hidden' : '' }}">
                        Le lien Wave pour ce montant n'est pas encore disponible. Vous pouvez payer en espèces auprès du Jewrine finance.
                    </p>
                </div>

                <div>
                    <p class="font-semibold text-emerald-900">3. Indiquez la référence de votre reçu Wave</p>
                    <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                        <input type="tel" name="numero_wave" value="{{ old('numero_wave', $membre->telephone) }}" inputmode="tel" autocomplete="tel"
                               placeholder="Numéro Wave utilisé" title="Numéro Wave utilisé pour payer"
                               class="rounded-lg border border-stone-300 px-3 py-2.5 text-base sm:w-44">
                        <input type="text" name="reference" value="{{ old('reference') }}" required autocomplete="off"
                               placeholder="Référence de la transaction"
                               class="flex-1 rounded-lg border border-stone-300 px-3 py-2.5 font-mono text-base">
                        <button type="submit" class="rounded-lg bg-emerald-800 px-5 py-2.5 font-semibold text-white hover:bg-emerald-900">
                            J'ai payé
                        </button>
                    </div>
                    @error('numero_wave')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                    @error('reference')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                    @error('nombre_mois')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
            </form>

            <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
                (() => {
                    const bouton = document.getElementById('bouton-wave');
                    const montant = document.getElementById('montant-wave');
                    const absent = document.getElementById('lien-absent');

                    document.querySelectorAll('input[name="nombre_mois"]').forEach((choix) => {
                        choix.addEventListener('change', () => {
                            const lien = choix.dataset.lienWave;
                            montant.textContent = choix.dataset.montant;

                            if (lien) {
                                bouton.href = lien;
                                bouton.classList.remove('hidden');
                                absent.classList.add('hidden');
                            } else {
                                bouton.href = '#';
                                bouton.classList.add('hidden');
                                absent.classList.remove('hidden');
                            }
                        });
                    });
                })();
            </script>
        @endif
    </div>

    {{-- Historique --}}
    @if ($historique->isNotEmpty())
        <div class="mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-emerald-900">Mes derniers paiements</h2>
                <a href="{{ route('recus.index') }}" class="text-xs font-medium text-emerald-800 underline">Tous mes reçus</a>
            </div>
            <ul class="mt-3 divide-y divide-stone-100 text-sm">
                @foreach ($historique as $paiement)
                    <li class="flex items-center justify-between gap-2 py-2">
                        <span>
                            {{ $paiement->declare_le->format('d/m/Y') }} · {{ $paiement->nombre_mois }} mois · {{ $paiement->moyen->getLabel() }}
                        </span>
                        <span class="text-right">
                            <span class="font-semibold">{{ $fcfa($paiement->montant) }} · {{ $paiement->statut->getLabel() }}</span>
                            @if ($paiement->numero_recu)
                                <a href="{{ route('recus.show', $paiement) }}" target="_blank" class="ml-2 text-xs font-medium text-emerald-800 underline">Reçu</a>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <a href="{{ route('espace') }}" class="mt-6 inline-block text-sm text-emerald-800 underline">Retour à mon compte</a>
@endsection
