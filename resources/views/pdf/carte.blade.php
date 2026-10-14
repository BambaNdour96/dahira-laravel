<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 20mm; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1c1917; }
        h1 { font-size: 13pt; color: #064e3b; margin: 0 0 4mm; }
        p.aide { font-size: 8.5pt; color: #57534e; margin: 0 0 8mm; }
        .carte { width: 85.6mm; height: 54mm; border: 0.3mm solid #a8a29e; border-radius: 3mm; overflow: hidden; position: relative; }
        .entete { height: 11mm; padding: 1.8mm 3mm 0; }
        .entete img { width: 8mm; height: 8mm; border-radius: 4mm; vertical-align: middle; }
        .entete .titre { display: inline-block; vertical-align: middle; margin-left: 2mm; }
        .nom-daara { font-size: 6.5pt; font-weight: bold; text-transform: uppercase; }
        .sous-titre { font-size: 5pt; font-weight: bold; color: #047857; letter-spacing: 0.6pt; text-transform: uppercase; }
        .bande { height: 1.2mm; }
        .bande .vert { float: left; width: 66%; height: 1.2mm; background: #047857; }
        .bande .or { float: left; width: 34%; height: 1.2mm; background: #d4a017; }
        table.corps { width: 100%; border-collapse: collapse; margin-top: 2mm; }
        table.corps td { vertical-align: middle; padding: 0 2.5mm; }
        .photo { width: 17mm; height: 22mm; border: 0.5mm solid #047857; border-radius: 1.5mm; }
        .prenom { font-size: 7pt; font-weight: bold; color: #44403c; }
        .nom { font-size: 10pt; font-weight: bold; color: #064e3b; }
        .etiquette { font-size: 4.8pt; font-weight: bold; color: #047857; text-transform: uppercase; }
        .valeur { font-size: 6.5pt; font-weight: bold; }
        .qr { width: 20mm; height: 20mm; }
        .pied { position: absolute; bottom: 0; left: 0; right: 0; height: 5mm; background: #065f46; color: #fff; font-size: 5.5pt; font-weight: bold; padding: 1.2mm 3mm 0; }
        .pied .droite { float: right; color: #fde68a; }
    </style>
</head>
<body>
    <h1>Carte de membre – Daara Nouroud Daareyni Kaolack</h1>
    <p class="aide">Carte à sa taille réelle (85,6 × 54 mm). Imprimez à 100 %, sans ajuster à la page. Le QR code permet de vérifier l'adhésion.</p>

    <div class="carte">
        <div class="entete">
            @if ($logo)<img src="{{ $logo }}" alt="">@endif
            <div class="titre">
                <div class="nom-daara">Daara Nouroud Daareyni Kaolack</div>
                <div class="sous-titre">Carte de membre · NDK</div>
            </div>
        </div>
        <div class="bande"><div class="vert"></div><div class="or"></div></div>

        <table class="corps">
            <tr>
                <td style="width: 19mm;">
                    @if ($photo)<img class="photo" src="{{ $photo }}" alt="">@endif
                </td>
                <td>
                    <div class="prenom">{{ $membre->prenom }}</div>
                    <div class="nom">{{ $membre->nom }}</div>
                    <div class="etiquette">Matricule</div>
                    <div class="valeur">{{ $membre->matricule }}</div>
                    <div class="etiquette">Cellule</div>
                    <div class="valeur">{{ $membre->cellule->nom }}</div>
                </td>
                <td style="width: 21mm;"><img class="qr" src="{{ $qr }}" alt=""></td>
            </tr>
        </table>

        <div class="pied">
            Membre depuis {{ $membre->date_adhesion?->format('Y') ?? now()->format('Y') }}
            <span class="droite">Vérifiable par QR code</span>
        </div>
    </div>
</body>
</html>
