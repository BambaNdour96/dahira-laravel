<?php

namespace App\Support;

use App\Models\Membre;
use Illuminate\Support\Carbon;

/**
 * Textes des relances de cotisation. Le bureau peut les modifier ici.
 */
final class MessagesRelance
{
    /** @param  list<Carbon>  $retard */
    public static function individuel(Membre $membre, array $retard, int $mensuel): string
    {
        $mois = collect($retard)->map(fn (Carbon $m): string => $m->translatedFormat('F Y'))->join(', ', ' et ');
        $montant = number_format(count($retard) * $mensuel, 0, ',', ' ');

        return 'Assalamou aleykoum '.$membre->prenom.', la Daara Nouroud Daareyni Kaolack vous rappelle que '
            .'vos cotisations de '.$mois.' ('.$montant.' FCFA) restent à régler. '
            .'Vous pouvez payer directement depuis votre compte : '.route('cotisations').' Jërëjëf !';
    }

    public static function groupe(): string
    {
        return 'Assalamou aleykoum à tous. Rappel de la Daara Nouroud Daareyni Kaolack : '
            .'pensez à vérifier vos cotisations dans votre compte et à régler les mois en attente : '
            .route('cotisations').' Jërëjëf !';
    }
}
