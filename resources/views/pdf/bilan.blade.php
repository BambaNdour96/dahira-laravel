@php
    $fcfa = fn (int $montant): string => number_format($montant, 0, ',', ' ').' FCFA';
    $tableaux = [
        'Recettes par source' => $bilan['recettes_par_source'], 'Recettes par programme' => $bilan['recettes_par_programme'],
        'Recettes par cellule' => $bilan['recettes_par_cellule'], 'Recettes par mois' => $bilan['recettes_par_mois'],
        'Dépenses par libellé' => $bilan['depenses_par_libelle'], 'Dépenses par rattachement' => $bilan['depenses_par_rattachement'],
        'Dépenses par projet' => $bilan['depenses_par_projet'], 'Dépenses par mois' => $bilan['depenses_par_mois'],
    ];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 14mm 14mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1c1917; }
        .entete { border-bottom: 2px solid #047857; padding-bottom: 3mm; margin-bottom: 4mm; }
        .entete img { width: 13mm; height: 13mm; vertical-align: middle; }
        .entete .titre { display: inline-block; vertical-align: middle; margin-left: 3mm; }
        .daara { font-size: 8pt; font-weight: bold; color: #047857; text-transform: uppercase; }
        h1 { font-size: 15pt; margin: 0; color: #064e3b; }
        h2 { font-size: 10.5pt; color: #fff; background: #065f46; padding: 1.5mm 2.5mm; margin: 5mm 0 2mm; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 7.5pt; color: #57534e; border-bottom: 1px solid #a8a29e; padding: 1.2mm; }
        td { padding: 1.2mm; border-bottom: 0.3px solid #d6d3d1; }
        td.n, th.n { text-align: right; }
        .chiffres td { text-align: center; border: 0.5px solid #d6d3d1; padding: 2mm; }
        .chiffres .v { font-size: 12pt; font-weight: bold; color: #064e3b; }
        .petit { font-size: 7pt; color: #57534e; }
        .alerte { border: 0.5px solid #b45309; color: #92400e; padding: 1.5mm 2mm; margin-bottom: 1.5mm; font-size: 8pt; }
        .provisoire { border: 0.5px solid #b91c1c; color: #b91c1c; font-weight: bold; padding: 1.5mm 2mm; margin-bottom: 3mm; font-size: 8pt; }
    </style>
</head>
<body>
    <div class="entete">
        @if ($logo)
            <img src="{{ $logo }}" alt="">
        @endif
        <div class="titre">
            <div class="daara">Daara Nouroud Daareyni Kaolack · Bilan financier</div>
            <h1>Exercice {{ $exercice->libelle }}</h1>
            <div class="petit">Du {{ $exercice->date_debut->format('d/m/Y') }} au {{ $exercice->date_fin->format('d/m/Y') }} · édité le {{ now()->format('d/m/Y à H:i') }}</div>
        </div>
    </div>

    @if ($exercice->estClos())
        <p class="petit">Exercice clos le {{ $exercice->cloture_le?->format('d/m/Y') }} : bilan figé à la clôture. Une correction faite ensuite compte dans l'exercice suivant.</p>
    @else
        <div class="provisoire">Bilan provisoire : l'exercice n'est pas encore clos.</div>
    @endif

    <h2>Résumé</h2>
    <table class="chiffres">
        <tr>
            <td><div class="v">{{ $fcfa($bilan['recettes']) }}</div><div class="petit">Recettes</div></td>
            <td><div class="v">{{ $fcfa($bilan['depenses']) }}</div><div class="petit">Dépenses</div></td>
            <td><div class="v">{{ $fcfa($bilan['recettes'] - $bilan['depenses']) }}</div><div class="petit">Résultat de l'exercice</div></td>
            <td><div class="v">{{ $fcfa(array_sum($bilan['caisses'])) }}</div><div class="petit">Trésorerie à la fin</div></td>
        </tr>
    </table>

    @if (! empty($bilan['alertes']))
        <h2>Points ouverts à la clôture</h2>
        @foreach ($bilan['alertes'] as $alerte)
            <div class="alerte">{{ $alerte }}</div>
        @endforeach
    @endif

    <h2>Trésorerie par caisse au {{ $exercice->date_fin->format('d/m/Y') }}</h2>
    <table>
        <tr><th>Caisse</th><th class="n">Solde</th></tr>
        @foreach ($bilan['caisses'] as $caisse => $solde)
            <tr><td>{{ $caisse }}</td><td class="n">{{ $fcfa($solde) }}</td></tr>
        @endforeach
    </table>

    @foreach ($tableaux as $titre => $lignes)
        @if ($lignes !== [])
            <h2>{{ $titre }}</h2>
            <table>
                @foreach ($lignes as $cle => $montant)
                    <tr><td>{{ $cle }}</td><td class="n">{{ $fcfa($montant) }}</td></tr>
                @endforeach
                <tr><td><strong>Total</strong></td><td class="n"><strong>{{ $fcfa(array_sum($lignes)) }}</strong></td></tr>
            </table>
        @endif
    @endforeach

    @if ($bilan['reaffectations'] !== [])
        <h2>Réaffectations de fonds</h2>
        <table>
            <tr><th>Numéro</th><th>Origine</th><th>Destination</th><th>Motif</th><th class="n">Montant</th></tr>
            @foreach ($bilan['reaffectations'] as $reaffectation)
                <tr>
                    <td>{{ $reaffectation['numero'] }}</td><td>{{ $reaffectation['origine'] }}</td><td>{{ $reaffectation['destination'] }}</td>
                    <td>{{ $reaffectation['motif'] }}</td><td class="n">{{ $fcfa($reaffectation['montant']) }}</td>
                </tr>
            @endforeach
        </table>
    @endif
</body>
</html>
