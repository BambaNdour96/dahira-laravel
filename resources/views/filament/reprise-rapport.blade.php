@php
    $import = $getRecord();
    $couleurs = ['prete' => 'text-success-600', 'importee' => 'text-success-600', 'doublon' => 'text-warning-600', 'erreur' => 'text-danger-600'];
@endphp

<div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
    <table class="w-full text-left text-sm">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5">
            <tr>
                <th class="px-3 py-2">Ligne</th>
                <th class="px-3 py-2">Membre</th>
                <th class="px-3 py-2">État</th>
                <th class="px-3 py-2">Messages</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
            @foreach ($import->rapport ?? [] as $ligne)
                <tr>
                    <td class="px-3 py-2 font-mono">{{ $ligne['numero'] }}</td>
                    <td class="px-3 py-2">{{ $ligne['nom'] }}</td>
                    <td class="px-3 py-2 font-semibold {{ $couleurs[$ligne['etat']] ?? '' }}">{{ \App\Models\ImportMembres::ETATS[$ligne['etat']] ?? $ligne['etat'] }}</td>
                    <td class="px-3 py-2">{{ implode(' ', $ligne['messages']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
