<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 20mm; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1c1917; }
        h1 { font-size: 13pt; color: #064e3b; margin: 0 0 4mm; }
        p.aide { font-size: 8.5pt; color: #57534e; margin: 0 0 8mm; }
        @include('pdf.partials.carte-styles')
    </style>
</head>
<body>
    <h1>Carte de membre – Daara Nouroud Daareyni Kaolack</h1>
    <p class="aide">Carte à sa taille réelle (85,6 × 54 mm). Imprimez à 100 %, sans ajuster à la page. Le QR code permet de vérifier l'adhésion.</p>

    @include('pdf.partials.carte', ['membre' => $membre, 'photo' => $photo, 'qr' => $qr, 'logo' => $logo, 'annee' => $annee ?? null])
</body>
</html>
