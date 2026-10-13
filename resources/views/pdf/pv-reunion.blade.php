<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 16mm 16mm 18mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #1c1917; line-height: 1.45; }
        .entete { border-bottom: 2px solid #047857; padding-bottom: 3mm; margin-bottom: 4mm; }
        .entete img { width: 15mm; height: 15mm; vertical-align: middle; }
        .entete .titre { display: inline-block; vertical-align: middle; margin-left: 3mm; }
        .daara { font-size: 8pt; font-weight: bold; color: #047857; text-transform: uppercase; }
        h1 { font-size: 14pt; margin: 0; color: #064e3b; }
        h2 { font-size: 10.5pt; color: #fff; background: #065f46; padding: 1.4mm 2.5mm; margin: 5mm 0 2mm; }
        .meta td { padding: 0.8mm 3mm 0.8mm 0; vertical-align: top; }
        .meta td.l { font-weight: bold; color: #064e3b; width: 38mm; }
        .point { margin-bottom: 3mm; }
        .point .t { font-weight: bold; color: #064e3b; }
        table.liste { width: 100%; border-collapse: collapse; }
        .liste th { text-align: left; font-size: 8pt; color: #57534e; border-bottom: 1px solid #a8a29e; padding: 1.3mm; }
        .liste td { padding: 1.3mm; border-bottom: 0.3px solid #d6d3d1; vertical-align: top; }
        .num { font-weight: bold; color: #064e3b; white-space: nowrap; }
        .petit { font-size: 8pt; color: #57534e; }
        .pied { position: fixed; bottom: -10mm; left: 0; right: 0; text-align: center; font-size: 7pt; color: #78716c; }
    </style>
</head>
<body>
    <div class="pied">{{ $reunion->instance->nom }} · PV du {{ $reunion->debut->format('d/m/Y') }} · Document interne</div>
    <div class="entete">
        @if ($logo)
            <img src="{{ $logo }}" alt="">
        @endif
        <div class="titre">
            <div class="daara">Daara Nouroud Daareyni Kaolack · {{ $reunion->instance->nom }}</div>
            <h1>Procès-verbal : {{ $reunion->titre }}</h1>
        </div>
    </div>

    <table class="meta">
        <tr><td class="l">Date</td><td>{{ $reunion->debut->translatedFormat('l j F Y à H\hi') }}</td></tr>
        <tr><td class="l">Lieu</td><td>{{ $reunion->lieu }}{{ $reunion->lien ? ' — '.$reunion->lien : '' }}</td></tr>
        <tr><td class="l">Président</td><td>{{ $reunion->instance->president ? $reunion->instance->president->prenom.' '.$reunion->instance->president->nom : '—' }}</td></tr>
        <tr><td class="l">Secrétaire de séance</td><td>{{ $reunion->secretaire ? $reunion->secretaire->prenom.' '.$reunion->secretaire->nom : '—' }}</td></tr>
        <tr><td class="l">Quorum</td><td>{{ $quorum['presents'] }} présent(s) sur {{ $quorum['membres'] }} membre(s) · {{ $quorum['atteint'] ? 'atteint' : 'non atteint' }} ({{ $quorum['requis'] }} requis)</td></tr>
        <tr><td class="l">Présents</td><td>{{ $reunion->presences->where('statut', 'present')->map(fn ($presence) => $presence->membre->prenom.' '.$presence->membre->nom)->implode(', ') ?: '—' }}</td></tr>
        <tr><td class="l">Excusés</td><td>{{ $reunion->presences->where('statut', 'excuse')->map(fn ($presence) => $presence->membre->prenom.' '.$presence->membre->nom)->implode(', ') ?: '—' }}</td></tr>
    </table>

    <h2>Ordre du jour et échanges</h2>
    @forelse ($reunion->points as $point)
        <div class="point">
            <div class="t">{{ $loop->iteration }}. {{ $point->titre }}</div>
            <div>{!! nl2br(e($point->resume ?? '')) !!}</div>
        </div>
    @empty
        <p class="petit">Aucun point inscrit.</p>
    @endforelse

    <h2>Décisions</h2>
    @if ($reunion->decisions->isEmpty())
        <p class="petit">Aucune décision.</p>
    @else
        <table class="liste">
            <tr><th style="width: 34mm">N°</th><th>Décision</th></tr>
            @foreach ($reunion->decisions as $decision)
                <tr>
                    <td class="num">{{ $decision->numero }}</td>
                    <td>{!! nl2br(e($decision->texte)) !!}
                        @if ($decision->vote())
                            <div class="petit">{{ $decision->vote() }}</div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>Actions</h2>
    @if ($reunion->actions->isEmpty())
        <p class="petit">Aucune action.</p>
    @else
        <table class="liste">
            <tr><th>Quoi</th><th style="width: 48mm">Qui</th><th style="width: 24mm">Pour quand</th></tr>
            @foreach ($reunion->actions as $action)
                <tr>
                    <td>{{ $action->description }}</td>
                    <td>{{ $action->responsableTexte() }}</td>
                    <td>{{ $action->echeance?->format('d/m/Y') ?? '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if (filled($reunion->divers))
        <h2>Divers</h2>
        <div>{!! nl2br(e($reunion->divers)) !!}</div>
    @endif

    <p class="petit" style="margin-top: 6mm">
        {{ $reunion->estPubliee() ? 'PV publié le '.$reunion->publiee_le?->format('d/m/Y à H:i').'.' : 'Brouillon, non publié.' }}
    </p>
</body>
</html>
