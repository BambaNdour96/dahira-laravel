<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 14mm 15mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #1c1917; }
        .entete { border-bottom: 2px solid #047857; padding-bottom: 3mm; margin-bottom: 4mm; }
        .entete img { width: 13mm; height: 13mm; vertical-align: middle; }
        .entete .titre { display: inline-block; vertical-align: middle; margin-left: 3mm; }
        .entete .daara { font-size: 8pt; font-weight: bold; color: #047857; text-transform: uppercase; letter-spacing: 0.5pt; }
        h1 { font-size: 14pt; margin: 0; color: #064e3b; }
        .aide { font-size: 8pt; color: #57534e; margin: 0 0 3mm; }
        h2 { font-size: 10.5pt; color: #fff; background: #065f46; padding: 1.5mm 2.5mm; margin: 4mm 0 2mm; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1.4mm 1.5mm; vertical-align: top; border-bottom: 0.3px solid #d6d3d1; }
        td.libelle { width: 36%; font-weight: bold; color: #064e3b; }
        .case { font-size: 11pt; }
        .petit { font-size: 7.5pt; color: #57534e; }
        .ligne { border-bottom: 0.5px dotted #78716c; display: inline-block; width: 60mm; height: 4mm; }
        .ligne-longue { border-bottom: 0.5px dotted #78716c; height: 6mm; }
        .signatures td { border: 0.5px solid #a8a29e; height: 18mm; width: 25%; font-size: 8pt; font-weight: bold; color: #064e3b; }
    </style>
</head>
<body>
    <div class="entete">
        @if ($logo)<img src="{{ $logo }}" alt="">@endif
        <div class="titre">
            <div class="daara">Daara Nouroud Daareyni Kaolack</div>
            <h1>Fiche de paramétrage d'une collecte</h1>
        </div>
    </div>
    <p class="aide">À remplir par le bureau : cochez les choix et complétez les montants. Le super administrateur crée ensuite la collecte dans la plateforme à partir de cette fiche.</p>

    <h2>1. Identité de la collecte</h2>
    <table>
        <tr><td class="libelle">Titre</td><td><div class="ligne-longue"></div></td></tr>
        <tr>
            <td class="libelle">Programme</td>
            <td>
                @foreach ($programmes as $programme)
                    <span class="case">☐</span> {{ $programme->getLabel() }}&nbsp;&nbsp;
                @endforeach
            </td>
        </tr>
        <tr><td class="libelle">Description (visible par les membres)</td><td><div class="ligne-longue"></div><div class="ligne-longue"></div></td></tr>
        <tr><td class="libelle">Événement lié (pour le scan des présences)</td><td><span class="case">☐</span> Aucun &nbsp; <span class="case">☐</span> Oui : <span class="ligne"></span></td></tr>
    </table>

    <h2>2. Engagement des membres</h2>
    <table>
        @foreach ($modes as $mode)
            <tr>
                <td class="libelle"><span class="case">☐</span> {{ $mode->getLabel() }}</td>
                <td class="petit">{{ $mode->getDescription() }}</td>
            </tr>
        @endforeach
        <tr><td class="libelle">Minimum ou montant identique</td><td><span class="ligne"></span> FCFA</td></tr>
        <tr><td class="libelle">Membres à qui le bureau attribue un montant</td><td><div class="ligne-longue"></div><div class="ligne-longue"></div></td></tr>
        <tr><td class="libelle">Objectif total de la collecte</td><td><span class="ligne"></span> FCFA &nbsp; <span class="case">☐</span> Pas d'objectif</td></tr>
    </table>

    <h2>3. Dates et règles</h2>
    <table>
        <tr><td class="libelle">Ouverture</td><td><span class="ligne"></span></td></tr>
        <tr><td class="libelle">Date limite</td><td><span class="ligne"></span> &nbsp; <span class="case">☐</span> Pas de date limite</td></tr>
        <tr><td class="libelle">Participation</td><td><span class="case">☐</span> Obligatoire &nbsp;&nbsp; <span class="case">☐</span> Volontaire</td></tr>
        <tr><td class="libelle">Versements après la date limite</td><td><span class="case">☐</span> Acceptés (le membre apparaît en retard) &nbsp; <span class="case">☐</span> Refusés</td></tr>
        <tr>
            <td class="libelle">Membres concernés</td>
            <td>
                <span class="case">☐</span> Toute la Daara &nbsp;
                @foreach ($cellules as $cellule)
                    <span class="case">☐</span> Cellule {{ $cellule }}&nbsp;
                @endforeach
            </td>
        </tr>
    </table>

    <h2>4. Visibilité</h2>
    <table>
        <tr><td class="libelle">Avancement de la collecte (jauge)</td><td><span class="case">☐</span> Bureau seulement &nbsp; <span class="case">☐</span> Bureau et membres (sans les noms)</td></tr>
        <tr><td class="libelle">Jauge visible par les membres à partir du</td><td><span class="ligne"></span> &nbsp; <span class="case">☐</span> Dès l'ouverture</td></tr>
        <tr><td class="libelle">Remarques du bureau</td><td><div class="ligne-longue"></div><div class="ligne-longue"></div></td></tr>
    </table>

    <h2>5. Validation</h2>
    <table class="signatures">
        <tr>
            <td>Jewrine national</td>
            <td>Secrétaire général</td>
            <td>Jewrine finance</td>
            <td>Super administrateur<br><span class="petit">Saisie le : ……/……/……</span></td>
        </tr>
    </table>
</body>
</html>
