@php
    [$sante, $raison] = $diagnostic;
    $fcfa = fn (?int $montant): string => $montant === null ? '—' : number_format($montant, 0, ',', ' ').' FCFA';
    $couleurs = ['vert' => '#047857', 'orange' => '#b45309', 'rouge' => '#b91c1c', 'clos' => '#57534e'];
@endphp
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
        .daara { font-size: 8pt; font-weight: bold; color: #047857; text-transform: uppercase; }
        h1 { font-size: 14pt; margin: 0; color: #064e3b; }
        h2 { font-size: 10.5pt; color: #fff; background: #065f46; padding: 1.5mm 2.5mm; margin: 5mm 0 2mm; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 8pt; color: #57534e; border-bottom: 1px solid #a8a29e; padding: 1.2mm; }
        td { padding: 1.2mm; vertical-align: top; border-bottom: 0.3px solid #d6d3d1; }
        td.libelle { width: 35%; font-weight: bold; color: #064e3b; }
        .sante { font-weight: bold; padding: 1mm 2mm; color: #fff; }
        .chiffres td { text-align: center; border: 0.5px solid #d6d3d1; }
        .chiffres .valeur { font-size: 13pt; font-weight: bold; color: #064e3b; }
        .petit { font-size: 7.5pt; color: #57534e; }
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
            <div class="daara">Daara Nouroud Daareyni Kaolack · Commission Suivi et Évaluation</div>
            <h1>Rapport du projet « {{ $projet->titre }} »</h1>
            <div class="petit">Édité le {{ now()->format('d/m/Y à H:i') }}</div>
        </div>
    </div>

    <p><span class="sante" style="background: {{ $couleurs[$sante->value] }};">{{ $sante->getLabel() }}</span> &nbsp; {{ $raison }}</p>

    <table class="chiffres">
        <tr>
            <td><div class="valeur">{{ $projet->avancement() }} %</div><div class="petit">Avancement</div></td>
            <td><div class="valeur">{{ $projet->tempsEcoule() }} %</div><div class="petit">Temps écoulé</div></td>
            <td><div class="valeur">{{ $fcfa($projet->depenses()) }}</div><div class="petit">Dépenses</div></td>
            <td><div class="valeur">{{ $fcfa($projet->budget) }}</div><div class="petit">Budget</div></td>
        </tr>
    </table>

    <h2>1. Le projet</h2>
    <table>
        <tr><td class="libelle">Objectif</td><td>{{ $projet->objectif }}</td></tr>
        @if ($projet->description)<tr><td class="libelle">Description</td><td>{{ $projet->description }}</td></tr>@endif
        <tr><td class="libelle">Commission</td><td>{{ $projet->commission?->nom ?? '—' }}</td></tr>
        <tr><td class="libelle">Responsable</td><td>{{ $projet->responsable ? $projet->responsable->prenom.' '.$projet->responsable->nom : '—' }}</td></tr>
        <tr><td class="libelle">Calendrier</td><td>Du {{ $projet->date_debut->format('d/m/Y') }} au {{ $projet->date_fin->format('d/m/Y') }}</td></tr>
        <tr><td class="libelle">État</td><td>{{ $projet->statut->getLabel() }}</td></tr>
        @if ($projet->collecte)<tr><td class="libelle">Financement</td><td>{{ $projet->collecte->titre }} : {{ $fcfa($projet->collecte->totalVerse()) }} collectés ({{ $projet->collecte->taux() }} %)</td></tr>@endif
    </table>

    <h2>2. Étapes</h2>
    <table>
        <tr><th>Étape</th><th>Prévue le</th><th>Réalisée le</th><th>État</th></tr>
        @forelse ($jalons as $jalon)
            <tr><td>{{ $jalon->titre }}</td><td>{{ $jalon->echeance->format('d/m/Y') }}</td><td>{{ $jalon->realise_le?->format('d/m/Y') ?? '—' }}</td><td>{{ $jalon->etat() }}</td></tr>
        @empty
            <tr><td colspan="4" class="petit">Aucune étape définie.</td></tr>
        @endforelse
    </table>

    <h2>3. Indicateurs</h2>
    <table>
        <tr><th>Indicateur</th><th>Cible</th><th>Atteint</th><th>Atteinte</th></tr>
        @forelse ($indicateurs as $indicateur)
            <tr><td>{{ $indicateur->libelle }}</td><td>{{ $indicateur->cible + 0 }} {{ $indicateur->unite }}</td><td>{{ $indicateur->valeur + 0 }} {{ $indicateur->unite }}</td><td>{{ $indicateur->atteinte() }} %</td></tr>
        @empty
            <tr><td colspan="4" class="petit">Aucun indicateur défini.</td></tr>
        @endforelse
    </table>

    <h2>4. Journal de suivi</h2>
    <table>
        <tr><th>Date</th><th>Type</th><th>Contenu</th><th>Montant</th></tr>
        @forelse ($journal as $entree)
            <tr><td>{{ $entree->created_at->format('d/m/Y') }}</td><td>{{ $entree->type->getLabel() }}</td><td>{{ $entree->texte }}</td><td>{{ $entree->montant !== null ? $fcfa($entree->montant) : '' }}</td></tr>
        @empty
            <tr><td colspan="4" class="petit">Aucune entrée.</td></tr>
        @endforelse
    </table>

    @if ($projet->resultat || $projet->bilan || $projet->lecons)
        <h2>5. Évaluation finale</h2>
        <table>
            <tr><td class="libelle">Résultat</td><td>{{ $projet->resultat?->getLabel() ?? '—' }}</td></tr>
            <tr><td class="libelle">Bilan</td><td>{{ $projet->bilan ?? '—' }}</td></tr>
            <tr><td class="libelle">Leçons retenues</td><td>{{ $projet->lecons ?? '—' }}</td></tr>
        </table>
    @endif
</body>
</html>
