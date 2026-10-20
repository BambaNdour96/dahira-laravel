@php
    $fcfa = fn (int $montant): string => number_format($montant, 0, ',', ' ').' FCFA';
    $tableaux = [
        'Recettes par source' => $rapport['recettes_par_source'], 'Recettes par programme' => $rapport['recettes_par_programme'],
        'Recettes par cellule' => $rapport['recettes_par_cellule'], 'Dépenses par libellé' => $rapport['depenses_par_libelle'],
        'Dépenses par rattachement' => $rapport['depenses_par_rattachement'], 'Dépenses par projet' => $rapport['depenses_par_projet'],
        'Trésorerie par caisse au '.\Illuminate\Support\Carbon::parse($rapport['fin'])->format('d/m/Y') => $rapport['caisses'],
    ];
@endphp
<x-filament-panels::page>
    <div class="flex flex-wrap items-end gap-4">
        <label class="text-sm">
            <span class="block font-medium text-gray-700 dark:text-gray-300">Exercice</span>
            <select wire:model.live="exercice" class="mt-1 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                <option value="">Dates libres</option>
                @foreach ($exercices as $id => $libelle)
                    <option value="{{ $id }}">{{ $libelle }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">
            <span class="block font-medium text-gray-700 dark:text-gray-300">Du</span>
            <input type="date" wire:model.live="debut" class="mt-1 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
        </label>
        <label class="text-sm">
            <span class="block font-medium text-gray-700 dark:text-gray-300">Au</span>
            <input type="date" wire:model.live="fin" class="mt-1 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
        </label>
        <x-filament::button tag="a" :href="$lienExcel" icon="heroicon-o-arrow-down-tray" color="gray">Excel</x-filament::button>
        @if ($lienExcelMembres)
            <x-filament::button tag="a" :href="$lienExcelMembres" icon="heroicon-o-users" color="gray">Excel avec le détail par membre</x-filament::button>
        @endif
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-filament::section>
            <div class="text-sm text-gray-500">Recettes</div>
            <div class="text-2xl font-bold text-success-600">{{ $fcfa($rapport['recettes']) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">Dépenses</div>
            <div class="text-2xl font-bold text-danger-600">{{ $fcfa($rapport['depenses']) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">Solde de la période</div>
            <div class="text-2xl font-bold">{{ $fcfa($rapport['recettes'] - $rapport['depenses']) }}</div>
        </x-filament::section>
    </div>

    <x-filament::section heading="Recettes et dépenses par mois">
        @if ($mois === [])
            <p class="text-sm text-gray-500">Aucun mouvement sur la période.</p>
        @else
            <div class="space-y-2">
                @foreach ($mois as $m)
                    @php
                        $recette = $rapport['recettes_par_mois'][$m] ?? 0;
                        $depense = $rapport['depenses_par_mois'][$m] ?? 0;
                    @endphp
                    <div class="grid grid-cols-[5rem_1fr] items-center gap-2 text-xs">
                        <span class="font-medium">{{ \Illuminate\Support\Carbon::parse($m.'-01')->translatedFormat('M Y') }}</span>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <div class="h-3 rounded bg-success-500" style="width: {{ max(0, round($recette / $plusGrand * 100)) }}%"></div>
                                <span>{{ $fcfa($recette) }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="h-3 rounded bg-danger-500" style="width: {{ round($depense / $plusGrand * 100) }}%"></div>
                                <span>{{ $fcfa($depense) }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-xs text-gray-500">En vert les recettes, en rouge les dépenses.</p>
        @endif
    </x-filament::section>

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($tableaux as $titre => $lignes)
            <x-filament::section :heading="$titre">
                @if ($lignes === [])
                    <p class="text-sm text-gray-500">Rien sur la période.</p>
                @else
                    <table class="w-full text-sm">
                        @foreach ($lignes as $cle => $montant)
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="py-1">{{ $cle }}</td>
                                <td class="py-1 text-right">{{ $fcfa($montant) }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td class="py-1 font-semibold">Total</td>
                            <td class="py-1 text-right font-semibold">{{ $fcfa(array_sum($lignes)) }}</td>
                        </tr>
                    </table>
                @endif
            </x-filament::section>
        @endforeach
    </div>

    @if ($rapport['reaffectations'] !== [])
        <x-filament::section heading="Réaffectations de fonds enregistrées">
            <table class="w-full text-sm">
                @foreach ($rapport['reaffectations'] as $reaffectation)
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <td class="py-1">{{ $reaffectation['numero'] }}</td>
                        <td class="py-1">{{ $reaffectation['origine'] }} → {{ $reaffectation['destination'] }}</td>
                        <td class="py-1">{{ $reaffectation['motif'] }}</td>
                        <td class="py-1 text-right">{{ $fcfa($reaffectation['montant']) }}</td>
                    </tr>
                @endforeach
            </table>
        </x-filament::section>
    @endif

    @if ($parMembre !== null)
        <x-filament::section heading="Détail par membre" description="Réservé au Jewrine finance : ce que chaque membre a versé sur la période.">
            @if ($parMembre === [])
                <p class="text-sm text-gray-500">Aucun versement de membre sur la période.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <tr class="text-left text-xs text-gray-500">
                            <th class="py-1">Membre</th>
                            @foreach ($sources as $source)
                                <th class="py-1 text-right">{{ $source }}</th>
                            @endforeach
                        </tr>
                        @foreach ($parMembre as $membre => $montants)
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="py-1">{{ $membre }}</td>
                                @foreach ($sources as $source)
                                    <td class="py-1 text-right">{{ isset($montants[$source]) ? $fcfa($montants[$source]) : '—' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
