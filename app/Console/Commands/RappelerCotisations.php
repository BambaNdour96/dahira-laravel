<?php

namespace App\Console\Commands;

use App\Enums\StatutMembre;
use App\Models\Membre;
use App\Notifications\RappelCotisation;
use App\Services\CotisationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * Chaque mois : un rappel par email aux membres en retard qui ont renseigné leur adresse.
 */
class RappelerCotisations extends Command
{
    protected $signature = 'cotisations:rappeler';

    protected $description = 'Envoie un rappel par email aux membres en retard de cotisation';

    public function handle(CotisationService $cotisations): int
    {
        $membres = Membre::query()
            ->where('statut', StatutMembre::Valide->value)
            ->whereNotNull('email')
            ->get();

        $retards = $cotisations->retards($membres);
        $envois = 0;

        foreach ($membres as $membre) {
            if (($retards[$membre->id] ?? 0) === 0 || blank($membre->email)) {
                continue;
            }

            $mois = collect($cotisations->moisEnRetard($membre))
                ->map(fn (Carbon $m): string => $m->translatedFormat('F Y'))
                ->values()
                ->all();

            Notification::route('mail', (string) $membre->email)->notify(new RappelCotisation(
                $membre->prenom,
                $mois,
                count($mois) * $cotisations->montantMensuel(),
            ));

            $envois++;
        }

        $this->info($envois.' rappel(s) envoyé(s).');

        return self::SUCCESS;
    }
}
