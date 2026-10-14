<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 15mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1c1917; }
        .entete { border-bottom: 2px solid #047857; padding-bottom: 3mm; margin-bottom: 4mm; }
        .entete img { width: 15mm; height: 15mm; vertical-align: middle; }
        .entete .titre { display: inline-block; vertical-align: middle; margin-left: 3mm; }
        .daara { font-size: 8pt; font-weight: bold; color: #047857; text-transform: uppercase; }
        h1 { font-size: 15pt; margin: 0; color: #064e3b; }
        h2 { font-size: 10.5pt; color: #fff; background: #065f46; padding: 1.4mm 2.5mm; margin: 5mm 0 2mm; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 8pt; color: #57534e; border-bottom: 1px solid #a8a29e; padding: 1.3mm; }
        td { padding: 1.3mm; border-bottom: 0.3px solid #d6d3d1; vertical-align: top; }
        .n { text-align: right; white-space: nowrap; }
        .chiffres td { text-align: center; border: 0.5px solid #d6d3d1; padding: 2mm; }
        .v { font-size: 12pt; font-weight: bold; color: #064e3b; }
        .petit { font-size: 7.5pt; color: #57534e; }
        .rouge { color: #b91c1c; font-weight: bold; }
        .lecons { border: 0.5px dashed #a8a29e; height: 45mm; }
    </style>
</head>
<body>
    @php
        $fcfa = fn (int $montant): string => number_format($montant, 0, ',', ' ').' FCFA';
    @endphp
    <div class="entete">
        @if ($logo)
            <img src="{{ $logo }}" alt="">
        @endif
        <div class="titre">
            <div class="daara">Daara Nouroud Daareyni Kaolack · Bilan</div>
            <h1>{{ $instance->nom }}</h1>
            <div class="petit">Président : {{ $instance->president ? $instance->president->prenom.' '.$instance->president->nom : '—' }} · édité le {{ now()->format('d/m/Y') }}</div>
        </div>
    </div>

    <h2>En chiffres</h2>
    <table class="chiffres">
        <tr>
            <td><div class="v">{{ $fcfa($budget['recettes']) }}</div><div class="petit">Recettes (collecte)</div></td>
            <td><div class="v">{{ $fcfa($budget['depenses']) }}</div><div class="petit">Dépenses validées</div></td>
            <td><div class="v {{ $budget['solde'] < 0 ? 'rouge' : '' }}">{{ $fcfa($budget['solde']) }}</div><div class="petit">Solde</div></td>
            <td><div class="v">{{ $avancement['faites'] }} / {{ $avancement['actions'] }}</div><div class="petit">Actions faites</div></td>
            <td><div class="v">{{ $presents ?? '—' }}</div><div class="petit">Présents au jour J</div></td>
        </tr>
    </table>

    <h2>Budget par commission</h2>
    <table>
        <tr><th>Commission</th><th>Chef</th><th class="n">Budget retenu</th><th class="n">Dépensé</th><th class="n">Reste</th><th class="n">Consommé</th></tr>
        @foreach ($instance->commissions as $index => $commission)
            @php
                $ligne = $budget['commissions'][$index] ?? ['budget' => 0, 'depense' => 0, 'reste' => 0, 'taux' => 0];
            @endphp
            <tr>
                <td>{{ $commission->nom }}</td>
                <td>{{ $commission->chef ? $commission->chef->prenom.' '.$commission->chef->nom : '—' }}</td>
                <td class="n">{{ $fcfa($ligne['budget']) }}</td>
                <td class="n">{{ $fcfa($ligne['depense']) }}</td>
                <td class="n {{ $ligne['reste'] < 0 ? 'rouge' : '' }}">{{ $fcfa($ligne['reste']) }}</td>
                <td class="n">{{ $ligne['taux'] }} %</td>
            </tr>
        @endforeach
        <tr><td colspan="2"><b>Total</b></td><td class="n"><b>{{ $fcfa($budget['budget']) }}</b></td><td class="n"><b>{{ $fcfa($budget['depenses']) }}</b></td><td class="n"><b>{{ $fcfa($budget['budget'] - $budget['depenses']) }}</b></td><td></td></tr>
    </table>

    <h2>Détail des dépenses</h2>
    <table>
        <tr><th>Date</th><th>Commission</th><th>Objet</th><th>Fournisseur</th><th>Justificatif</th><th class="n">Montant</th></tr>
        @forelse ($depenses as $depense)
            <tr>
                <td>{{ $depense->date_depense->format('d/m/Y') }}</td>
                <td>{{ $depense->commission->nom }}</td>
                <td>{{ $depense->objet }}</td>
                <td>{{ $depense->fournisseur ?? '—' }}</td>
                <td>{{ $depense->justificatif_path ? 'Oui' : 'Non' }}</td>
                <td class="n">{{ $fcfa($depense->montant) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="petit">Aucune dépense validée.</td></tr>
        @endforelse
    </table>

    <h2>Décisions</h2>
    <table>
        @forelse ($decisions as $decision)
            <tr><td style="width: 34mm"><b>{{ $decision->numero }}</b></td><td>{{ $decision->texte }}</td></tr>
        @empty
            <tr><td class="petit">Aucune décision.</td></tr>
        @endforelse
    </table>

    <h2>Leçons retenues pour le prochain comité</h2>
    <div class="lecons"></div>
</body>
</html>
