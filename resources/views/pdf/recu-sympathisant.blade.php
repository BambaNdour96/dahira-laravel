<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 12mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1c1917; }
        .entete { border-bottom: 2px solid #047857; padding-bottom: 3mm; margin-bottom: 5mm; }
        .entete img { width: 14mm; height: 14mm; vertical-align: middle; }
        .entete .titre { display: inline-block; vertical-align: middle; margin-left: 3mm; }
        .daara { font-size: 8pt; font-weight: bold; color: #047857; text-transform: uppercase; }
        h1 { font-size: 14pt; margin: 0; color: #064e3b; }
        td { padding: 1.5mm 2mm; }
        td.l { font-weight: bold; color: #064e3b; width: 45mm; }
        .montant { font-size: 16pt; font-weight: bold; color: #064e3b; }
        .merci { margin-top: 6mm; font-style: italic; color: #44403c; }
    </style>
</head>
<body>
    <div class="entete">
        @if ($logo)<img src="{{ $logo }}" alt="">@endif
        <div class="titre">
            <div class="daara">Daara Nouroud Daareyni Kaolack</div>
            <h1>Reçu de participation n° {{ $participation->numero_recu }}</h1>
        </div>
    </div>
    <table>
        <tr><td class="l">Reçu de</td><td>{{ $participation->sympathisant->nomComplet() }}</td></tr>
        <tr><td class="l">Programme</td><td>{{ $participation->collecte->titre }}</td></tr>
        <tr><td class="l">Montant</td><td class="montant">{{ number_format($participation->montant, 0, ',', ' ') }} FCFA</td></tr>
        <tr><td class="l">Moyen</td><td>{{ $participation->moyen->getLabel() }}</td></tr>
        <tr><td class="l">Date</td><td>{{ $participation->created_at->format('d/m/Y à H:i') }}</td></tr>
        <tr><td class="l">Reçu par</td><td>{{ $participation->auteur->name ?? '—' }}, Jewrine finance</td></tr>
    </table>
    <p class="merci">Barakallahou fikoum pour votre participation.</p>
</body>
</html>
