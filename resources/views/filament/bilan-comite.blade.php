@php
    $libelles = [
        'budget' => 'Budget', 'decaisse' => 'Décaissé', 'justifie' => 'Justifié', 'reste_a_justifier' => 'Reste à justifier', 'promis' => 'Promis',
        'encaisse' => 'Encaissé', 'disponible' => 'Disponible', 'enveloppe' => 'Enveloppe', 'taches' => 'Tâches', 'taches_faites' => 'Tâches faites',
        'reunions' => 'Réunions', 'decisions' => 'Décisions',
    ];
    $montants = ['budget', 'decaisse', 'justifie', 'reste_a_justifier', 'promis', 'encaisse', 'disponible', 'enveloppe'];
@endphp
<div class="space-y-4 text-sm">
    <p class="font-semibold">{{ \App\Models\BilanComite::APPRECIATIONS[$bilan->appreciation] ?? '—' }} · rendu par {{ $bilan->auteur->name }} le {{ $bilan->updated_at->format('d/m/Y') }}</p>
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
        @foreach ($bilan->chiffres ?? [] as $cle => $valeur)
            <div class="rounded-lg bg-gray-50 p-2 dark:bg-white/5">
                <div class="text-xs text-gray-500">{{ $libelles[$cle] ?? $cle }}</div>
                <div class="font-semibold">{{ in_array($cle, $montants, true) ? number_format($valeur, 0, ',', ' ').' FCFA' : $valeur }}</div>
            </div>
        @endforeach
    </div>
    @foreach (['forces' => 'Forces', 'faiblesses' => 'Faiblesses', 'ameliorations' => 'Améliorations pour la prochaine fois'] as $champ => $titre)
        @if ($bilan->{$champ})
            <div>
                <h3 class="font-semibold">{{ $titre }}</h3>
                <p class="whitespace-pre-line">{{ $bilan->{$champ} }}</p>
            </div>
        @endif
    @endforeach
</div>
