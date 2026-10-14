<x-filament-panels::page>
    <p class="text-sm text-gray-600 dark:text-gray-400">
        Les droits d'une personne sont l'addition de ses casquettes en cours. « Daara » : pour toute la Daara ;
        « Cellule » : seulement dans les cellules qu'elle dirige. Cette matrice se modifie dans le code
        (App\Support\Capacites), puis s'applique avec <code>php artisan droits:synchroniser</code>.
    </p>

    <div class="overflow-x-auto rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
        <table class="w-full text-xs">
            <thead class="bg-gray-50 dark:bg-white/5">
                <tr>
                    <th class="sticky left-0 bg-gray-50 p-2 text-left dark:bg-gray-900">Capacité</th>
                    @foreach ($casquettes as $libelle)
                        <th class="p-2 text-center font-semibold">{{ $libelle }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($catalogue as $domaine => $capacites)
                    <tr class="bg-primary-50 dark:bg-primary-500/10">
                        <td colspan="{{ count($casquettes) + 1 }}" class="p-2 font-bold">{{ $domaine }}</td>
                    </tr>
                    @foreach ($capacites as $code => $libelle)
                        <tr class="border-t border-gray-100 dark:border-white/5">
                            <td class="sticky left-0 bg-white p-2 dark:bg-gray-900">
                                <span class="font-medium">{{ $libelle }}</span>
                                <span class="block font-mono text-[10px] text-gray-500">{{ $code }}</span>
                            </td>
                            @foreach (array_keys($casquettes) as $casquette)
                                @php
                                    $portee = \App\Support\Capacites::portee($casquette, $code);
                                @endphp
                                <td class="p-2 text-center">
                                    @if ($portee === 'Daara')
                                        <span class="font-semibold text-success-600">Daara</span>
                                    @elseif ($portee === 'Cellule')
                                        <span class="font-semibold text-warning-600">Cellule</span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
