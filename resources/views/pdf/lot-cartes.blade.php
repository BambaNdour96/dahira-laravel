<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 8mm 15mm; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1c1917; }
        .entete-lot { font-size: 7pt; color: #57534e; margin: 0 0 1.5mm; height: 3mm; }
        table.planche { border-collapse: collapse; }
        table.planche td { padding: 0; }
        td.case { width: 85.6mm; height: 54mm; border: 0.1mm dashed #a8a29e; vertical-align: top; }
        td.gouttiere { width: 6mm; }
        .saut { page-break-after: always; }
        @include('pdf.partials.carte-styles')
        .carte { border: 0; border-radius: 0; }
    </style>
</head>
<body>
    @foreach ($pages as $page)
        <div class="{{ $loop->last ? '' : 'saut' }}">
            <p class="entete-lot">{{ $lot->numero }}{{ $lot->cellule ? ' · '.$lot->cellule->nom : '' }} · page {{ $loop->iteration }} sur {{ $loop->count }} · imprimer à 100 %, découper sur les pointillés</p>
            <table class="planche">
                @foreach ($page->values()->chunk(2) as $ligne)
                    <tr>
                        @foreach ($ligne->values() as $index => $carte)
                            @if ($index === 1)
                                <td class="gouttiere"></td>
                            @endif
                            <td class="case">
                                @include('pdf.partials.carte', ['membre' => $carte['membre'], 'photo' => $carte['photo'], 'qr' => $carte['qr'], 'logo' => $logo, 'annee' => $carte['annee']])
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>
    @endforeach
</body>
</html>
