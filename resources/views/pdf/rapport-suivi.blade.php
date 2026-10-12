@php
    $fcfa = fn (?int $montant): string => $montant === null ? '—' : number_format($montant, 0, ',', ' ').' FCFA';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 16mm 16mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1c1917; line-height: 1.45; }
        .entete { border-bottom: 2px solid #047857; padding-bottom: 3mm; margin-bottom: 5mm; }
        .entete img { width: 13mm; height: 13mm; vertical-align: middle; }
        .entete .titre { display: inline-block; vertical-align: middle; margin-left: 3mm; }
        .daara { font-size: 8pt; font-weight: bold; color: #047857; text-transform: uppercase; }
        h1 { font-size: 15pt; margin: 0; color: #064e3b; }
        h2.section { font-size: 11pt; color: #fff; background: #065f46; padding: 1.5mm 2.5mm; margin: 6mm 0 2mm; }
        .meta td { padding: 1mm 2mm 1mm 0; font-size: 9pt; }
        .meta td.l { font-weight: bold; color: #064e3b; width: 32mm; }
        .chiffres { width: 100%; border-collapse: collapse; margin-top: 2mm; }
        .chiffres td { text-align: center; border: 0.5px solid #d6d3d1; padding: 2mm; }
        .chiffres .v { font-size: 13pt; font-weight: bold; color: #064e3b; }
        .petit { font-size: 7.5pt; color: #57534e; }
        .texte h2 { font-size: 11pt; color: #064e3b; }
        .texte h3 { font-size: 10pt; color: #064e3b; }
        .texte blockquote { border-left: 2px solid #d4a017; margin-left: 0; padding-left: 3mm; color: #44403c; }
        .signature { margin-top: 10mm; font-size: 9pt; }
    </style>
</head>
<body>
    <div class="entete">
        @if ($logo)<img src="{{ $logo }}" alt="">@endif
        <div class="titre">
            <div class="daara">Daara Nouroud Daareyni Kaolack · Commission Suivi et Évaluation</div>
            <h1>{{ $rapport->titre }}</h1>
        </div>
    </div>

    <table class="meta">
        <tr><td class="l">Type</td><td>{{ $rapport->type->getLabel() }}</td></tr>
        <tr><td class="l">Sujet</td><td>{{ $rapport->sujet() }}</td></tr>
        @if ($rapport->periode_debut || $rapport->periode_fin)
            <tr><td class="l">Période</td><td>{{ $rapport->periode_debut?->format('d/m/Y') ?? '…' }} au {{ $rapport->periode_fin?->format('d/m/Y') ?? '…' }}</td></tr>
        @endif
        <tr><td class="l">Rédigé par</td><td>{{ $rapport->auteur?->name ?? '—' }}</td></tr>
        <tr><td class="l">État</td><td>{{ $rapport->estPublie() ? 'Publié le '.$rapport->publie_le?->format('d/m/Y') : 'Brouillon' }}</td></tr>
    </table>

    @if ($rapport->projet)
        @php [$sante, $raison] = $rapport->projet->diagnostic(); @endphp
        <h2 class="section">Chiffres du projet</h2>
        <table class="chiffres">
            <tr>
                <td><div class="v">{{ $rapport->projet->avancement() }} %</div><div class="petit">Avancement</div></td>
                <td><div class="v">{{ $rapport->projet->tempsEcoule() }} %</div><div class="petit">Temps écoulé</div></td>
                <td><div class="v">{{ $fcfa($rapport->projet->depenses()) }}</div><div class="petit">Dépenses / budget {{ $fcfa($rapport->projet->budget) }}</div></td>
                <td><div class="v">{{ $sante->getLabel() }}</div><div class="petit">{{ $raison }}</div></td>
            </tr>
        </table>
    @elseif ($rapport->collecte)
        <h2 class="section">Chiffres de la collecte</h2>
        <table class="chiffres">
            <tr>
                <td><div class="v">{{ $rapport->collecte->taux() }} %</div><div class="petit">Avancement</div></td>
                <td><div class="v">{{ $fcfa($rapport->collecte->totalVerse()) }}</div><div class="petit">Collecté</div></td>
                <td><div class="v">{{ $fcfa($rapport->collecte->totalEngage()) }}</div><div class="petit">Engagé</div></td>
                <td><div class="v">{{ $fcfa($rapport->collecte->objectif) }}</div><div class="petit">Objectif</div></td>
            </tr>
        </table>
    @endif

    <h2 class="section">Constats</h2>
    <div class="texte">{!! $contenu !!}</div>

    @if (trim(strip_tags($recommandations)) !== '')
        <h2 class="section">Recommandations au bureau</h2>
        <div class="texte">{!! $recommandations !!}</div>
    @endif

    <p class="signature">Édité le {{ now()->format('d/m/Y à H:i') }}.</p>
</body>
</html>
