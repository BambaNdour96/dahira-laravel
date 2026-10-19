{{-- Une carte de membre à sa taille réelle (85,6 × 54 mm), pour le PDF du membre et les lots de l'imprimeur. --}}
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
                <td style="width: 21mm;">
                    @if ($qr)
                        <img class="qr" src="{{ $qr }}" alt="">
                    @endif
                </td>
            </tr>
        </table>

        <div class="pied">
            {{ $annee ? 'Carte '.$annee : 'Membre depuis '.($membre->date_adhesion?->format('Y') ?? now()->format('Y')) }}
            <span class="droite">Vérifiable par QR code</span>
        </div>
    </div>
