<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1c1917; margin: 0; }
        .entete { background: #1e5223; color: #fff; padding: 14px 18px; border-bottom: 4px solid #d4a017; }
        .entete table { width: 100%; }
        .logo { width: 52px; height: 52px; border-radius: 26px; background: #fff; }
        .titre { font-size: 15px; font-weight: bold; }
        .sous-titre { font-size: 10px; color: #f5e0a0; letter-spacing: 2px; }
        .contenu { padding: 18px; }
        .numero { font-size: 16px; font-weight: bold; color: #1e5223; }
        table.infos { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.infos td { padding: 6px 4px; border-bottom: 1px solid #e7e5e4; }
        table.infos td.libelle { color: #78716c; width: 40%; }
        .montant { margin-top: 16px; padding: 12px; background: #f0f8f0; border: 1px solid #b9dfba; text-align: center; }
        .montant strong { font-size: 18px; color: #1e5223; }
        .pied { margin-top: 18px; font-size: 9px; color: #78716c; text-align: center; }
    </style>
</head>
<body>
    <div class="entete">
        <table>
            <tr>
                @if ($logo)
                    <td style="width: 60px;"><img src="{{ $logo }}" class="logo" alt=""></td>
                @endif
                <td>
                    <div class="titre">Daara Nouroud Daareyni Kaolack</div>
                    <div class="sous-titre">{{ mb_strtoupper($paiement->natureRecu()) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="contenu">
        <div class="numero">{{ $paiement->natureRecu() }} n° {{ $paiement->numero_recu }}</div>
        @if ($paiement->statut === \App\Enums\StatutPaiement::Contrepasse)
            <div style="margin: 3mm 0; padding: 2mm; border: 0.5mm solid #b91c1c; color: #b91c1c; font-weight: bold; text-align: center;">
                ANNULÉ le {{ $paiement->contrepasse_le?->format('d/m/Y') }} · {{ $paiement->motif_correction }}
            </div>
        @endif
        <div>Émis le {{ $paiement->traite_le?->format('d/m/Y à H:i') }}</div>

        <table class="infos">
            <tr><td class="libelle">Membre</td><td>{{ $paiement->membre->prenom }} {{ $paiement->membre->nom }}</td></tr>
            <tr><td class="libelle">Matricule</td><td>{{ $paiement->membre->matricule ?? '—' }}</td></tr>
            <tr><td class="libelle">Cellule</td><td>{{ $paiement->membre->cellule->nom }}</td></tr>
            <tr><td class="libelle">Objet</td><td>{{ $paiement->libelleObjet() }}</td></tr>
            @if ($paiement->periode())
                <tr><td class="libelle">Période couverte</td><td>{{ ucfirst($paiement->periode()) }}</td></tr>
            @endif
            <tr><td class="libelle">Moyen de paiement</td><td>{{ $paiement->moyen->getLabel() }}</td></tr>
            @if ($paiement->reference)
                <tr><td class="libelle">Référence Wave</td><td>{{ $paiement->reference }}</td></tr>
            @endif
            @if ($paiement->declarant !== null && $paiement->declarant->id !== $paiement->membre->user_id)
                <tr><td class="libelle">Versé par</td><td>{{ $paiement->declarant->name }}</td></tr>
            @endif
            <tr><td class="libelle">Confirmé par</td><td>{{ $paiement->traitePar?->name ?? 'Le bureau' }}</td></tr>
        </table>

        <div class="montant">
            Montant reçu<br>
            <strong>{{ number_format($paiement->montant, 0, ',', ' ') }} FCFA</strong>
        </div>

        <div class="pied">
            Ce reçu est généré par la plateforme des membres de la Daara Nouroud Daareyni Kaolack.<br>
            Jërëjëf pour votre contribution.
        </div>
    </div>
</body>
</html>
