<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 16mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #1c1917; }
        .entete { border-bottom: 2px solid #047857; padding-bottom: 3mm; margin-bottom: 5mm; }
        .entete img { width: 14mm; height: 14mm; vertical-align: middle; }
        .entete .titre { display: inline-block; vertical-align: middle; margin-left: 3mm; }
        .daara { font-size: 8pt; font-weight: bold; color: #047857; text-transform: uppercase; }
        h1 { font-size: 14pt; margin: 0; color: #064e3b; }
        .meta td { padding: 1mm 3mm 1mm 0; }
        .meta td.l { font-weight: bold; color: #064e3b; width: 45mm; }
        table.ops { width: 100%; border-collapse: collapse; margin-top: 5mm; }
        .ops th { text-align: left; font-size: 8pt; color: #57534e; border-bottom: 1px solid #a8a29e; padding: 1.5mm; }
        .ops td { padding: 1.5mm; border-bottom: 0.3px solid #d6d3d1; }
        .n { text-align: right; }
        .total td { font-weight: bold; font-size: 11pt; color: #064e3b; border-top: 1.5px solid #064e3b; }
        .signatures { width: 100%; margin-top: 14mm; }
        .signatures td { width: 50%; padding-top: 16mm; border-top: 0.5px solid #57534e; font-size: 8.5pt; text-align: center; }
    </style>
</head>
<body>
    <div class="entete">
        @if ($logo)
            <img src="{{ $logo }}" alt="">
        @endif
        <div class="titre">
            <div class="daara">Daara Nouroud Daareyni Kaolack · Trésorerie</div>
            <h1>Bordereau de remise de fonds n° {{ $remise->numero }}</h1>
        </div>
    </div>

    <table class="meta">
        <tr><td class="l">Cellule</td><td>{{ $remise->cellule->nom }}</td></tr>
        <tr><td class="l">Remis par</td><td>{{ $remise->representant->name }}, représentant du Jewrine finance</td></tr>
        <tr><td class="l">Reçu par</td><td>{{ $remise->receveur->name }}, Jewrine finance</td></tr>
        <tr><td class="l">Date</td><td>{{ $remise->created_at->format('d/m/Y à H:i') }}</td></tr>
        <tr><td class="l">Opérations</td><td>{{ $remise->nombre_operations }}</td></tr>
    </table>

    <table class="ops">
        <tr><th>Date</th><th>Membre</th><th>Objet</th><th>Reçu</th><th class="n">Montant</th></tr>
        @foreach ($remise->paiements as $paiement)
            <tr>
                <td>{{ $paiement->traite_le?->format('d/m/Y') }}</td>
                <td>{{ $paiement->membre->prenom }} {{ $paiement->membre->nom }}</td>
                <td>{{ $paiement->objet === 'cotisation' ? 'Cotisation ('.$paiement->nombre_mois.' mois)' : ($paiement->collecte->titre ?? 'Programme') }}</td>
                <td>{{ $paiement->numero_recu ?? '—' }}</td>
                <td class="n">{{ number_format($paiement->montant, 0, ',', ' ') }} FCFA</td>
            </tr>
        @endforeach
        <tr class="total"><td colspan="4">Total remis</td><td class="n">{{ number_format($remise->montant, 0, ',', ' ') }} FCFA</td></tr>
    </table>

    <table class="signatures">
        <tr><td>Le représentant</td><td>Le Jewrine finance</td></tr>
    </table>
</body>
</html>
