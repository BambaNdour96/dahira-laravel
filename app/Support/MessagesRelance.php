<?php

namespace App\Support;

use App\Models\Collecte;
use App\Models\Engagement;
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

    /** Relance individuelle pour une collecte (Magal, Ziar, Prokhane…). */
    public static function collecte(Engagement $engagement): string
    {
        $fcfa = fn (int $montant): string => number_format($montant, 0, ',', ' ').' FCFA';

        return 'Assalamou aleykoum '.$engagement->membre->prenom.', la Daara Nouroud Daareyni Kaolack vous rappelle '
            .'votre engagement pour « '.$engagement->collecte->titre.' » : '.$fcfa($engagement->verse()).' versés sur '
            .$fcfa($engagement->montant).', il reste '.$fcfa($engagement->reste()).'. '
            .'Vous pouvez verser directement depuis votre compte : '.route('engagements.index').' Jërëjëf !';
    }

    /** Message à publier dans les groupes WhatsApp de la Daara pour une collecte. */
    public static function collecteGroupe(Collecte $collecte): string
    {
        $limite = $collecte->date_limite !== null ? ' avant le '.$collecte->date_limite->translatedFormat('j F Y') : '';

        return 'Assalamou aleykoum à tous. La Daara Nouroud Daareyni Kaolack vous rappelle « '.$collecte->titre.' ». '
            .'Pensez à fixer votre engagement et à verser votre participation'.$limite.', directement depuis votre compte : '
            .route('engagements.index').' Qu\'Allah accepte et récompense chacun. Jërëjëf !';
    }

    public static function groupe(): string
    {
        return 'Assalamou aleykoum à tous. Rappel de la Daara Nouroud Daareyni Kaolack : '
            .'pensez à vérifier vos cotisations dans votre compte et à régler les mois en attente : '
            .route('cotisations').' Jërëjëf !';
    }
}
