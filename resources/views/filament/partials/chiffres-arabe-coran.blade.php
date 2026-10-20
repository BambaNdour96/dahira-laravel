<div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
    <table class="w-full text-left text-sm">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5">
            <tr>
                <th class="px-3 py-2">Cellule</th>
                <th class="px-3 py-2">Rubrique remplie</th>
                <th class="px-3 py-2">Lisent couramment</th>
                <th class="px-3 py-2">Hafiz</th>
                <th class="px-3 py-2">Hizb mémorisés</th>
                <th class="px-3 py-2">Animent un kourel</th>
                <th class="px-3 py-2">Souhaitent apprendre</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
            @foreach ($chiffres as $ligne)
                <tr>
                    <td class="px-3 py-2 font-semibold">{{ $ligne['cellule'] }}</td>
                    <td class="px-3 py-2">{{ $ligne['remplies'] }} / {{ $ligne['membres'] }}</td>
                    <td class="px-3 py-2">{{ $ligne['lisent'] }}</td>
                    <td class="px-3 py-2">{{ $ligne['hafiz'] }}</td>
                    <td class="px-3 py-2">{{ $ligne['hizb'] }}</td>
                    <td class="px-3 py-2">{{ $ligne['kourel'] }}</td>
                    <td class="px-3 py-2">{{ $ligne['apprendre'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
