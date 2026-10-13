@php
    $fcfa = fn (?int $montant): string => $montant === null ? '—' : number_format($montant, 0, ',', ' ').' FCFA';
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
        .barre { background: #e7e5e4; height: 2.5mm; width: 30mm; }
        .barre div { background: #047857; height: 2.5mm; }
            .confidentiel { border: 0.5px solid #b91c1c; color: #b91c1c; font-size: 7.5pt; font-weight: bold; padding: 1.2mm 2mm; margin-bottom: 3mm; }
        .pied-confidentiel { position: fixed; bottom: -9mm; left: 0; right: 0; text-align: center; font-size: 6.5pt; color: #b91c1c; }
    </style>
</head>
<body>
    <div class="pied-confidentiel">{{ $confidentiel }}</div>
    <div class="confidentiel">{{ $confidentiel }}</div>
    <div class="entete">
        @if ($logo)<img src="{{ $logo }}" alt="">@endif
        <div class="titre">
            <div class="daara">Daara Nouroud Daareyni Kaolack · Rapport de programme</div>
            <h1>{{ $collecte->titre }}</h1>
            <div class="petit">{{ $collecte->programme->getLabel() }} · {{ $collecte->mode->getLabel() }} · {{ $collecte->statut->getLabel() }} · édité le {{ now()->format('d/m/Y à H:i') }}</div>
        </div>
    </div>

    <h2>💰 La collecte</h2>
    <table class="chiffres">
        <tr>
            <td><div class="v">{{ $chiffres['taux'] }} %</div><div class="petit">Avancement</div></td>
            <td><div class="v">{{ $fcfa($chiffres['total_verse']) }}</div><div class="petit">Collecté sur {{ $fcfa($chiffres['cible']) }}</div></td>
            <td><div class="v">{{ $chiffres['engages'] }} / {{ $chiffres['concernes'] }}</div><div class="petit">Membres engagés</div></td>
            <td><div class="v">{{ $chiffres['a_jour'] }}</div><div class="petit">À jour</div></td>
            <td><div class="v">{{ $chiffres['en_retard'] }}</div><div class="petit">En retard</div></td>
        </tr>
    </table>
    <p class="petit">Ouverture le {{ $collecte->ouverture->format('d/m/Y') }}@if ($collecte->date_limite) · date limite le {{ $collecte->date_limite->format('d/m/Y') }}@endif · total engagé : {{ $fcfa($chiffres['total_engage']) }}.</p>

    @if ($jourJ)
        <h2>📍 Le jour J</h2>
        <table class="chiffres">
            <tr>
                <td><div class="v">{{ $jourJ['evenement']->debut->format('d/m/Y') }}</div><div class="petit">{{ $jourJ['evenement']->debut->format('H:i') }}</div></td>
                <td><div class="v">{{ $jourJ['evenement']->lieu }}</div><div class="petit">Lieu</div></td>
                <td><div class="v">{{ $jourJ['presents'] }} / {{ $jourJ['concernes'] }}</div><div class="petit">Présents</div></td>
                <td><div class="v">{{ $jourJ['concernes'] > 0 ? round($jourJ['presents'] / $jourJ['concernes'] * 100) : 0 }} %</div><div class="petit">Taux de présence</div></td>
            </tr>
        </table>
    @endif

    <h2>Par cellule</h2>
    <table>
        <tr><th>Cellule</th><th class="n">Concernés</th><th class="n">Engagés</th><th class="n">À jour</th><th class="n">Engagé</th><th class="n">Versé</th><th>Avancement</th></tr>
        @foreach ($cellules as $nom => $ligne)
            <tr>
                <td>{{ $nom }}</td>
                <td class="n">{{ $ligne['concernes'] }}</td>
                <td class="n">{{ $ligne['engages'] }}</td>
                <td class="n">{{ $ligne['a_jour'] }}</td>
                <td class="n">{{ $fcfa($ligne['engage']) }}</td>
                <td class="n">{{ $fcfa($ligne['verse']) }}</td>
                <td><div class="barre"><div style="width: {{ $ligne['taux'] }}%"></div></div> {{ $ligne['taux'] }} %</td>
            </tr>
        @endforeach
    </table>

    <h2>Détail des engagements</h2>
    <table>
        <tr><th>Membre</th><th>Cellule</th><th class="n">Engagement</th><th class="n">Versé</th><th class="n">Reste</th><th>Situation</th></tr>
        @forelse ($engagements as $engagement)
            <tr>
                <td>{{ $engagement->membre->prenom }} {{ $engagement->membre->nom }}</td>
                <td>{{ $engagement->membre->cellule->nom }}</td>
                <td class="n">{{ $fcfa($engagement->montant) }}</td>
                <td class="n">{{ $fcfa($engagement->verse()) }}</td>
                <td class="n">{{ $fcfa($engagement->reste()) }}</td>
                <td>{{ $engagement->situation() }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="petit">Aucun engagement pour le moment.</td></tr>
        @endforelse
    </table>
</body>
</html>
