<?php

namespace App\Console\Commands;

use App\Enums\StatutMembre;
use App\Models\Connexion;
use App\Models\JournalActivite;
use App\Models\Membre;
use App\Services\DonneesPersonnellesService;
use Illuminate\Console\Command;

/**
 * Chaque nuit : applique les durées de conservation annoncées dans « Données personnelles ».
 */
class PurgerDonnees extends Command
{
    protected $signature = 'donnees:purger';

    protected $description = 'Efface les données arrivées au terme de leur durée de conservation';

    public function handle(DonneesPersonnellesService $donnees): int
    {
        $connexions = Connexion::query()->where('created_at', '<', now()->subMonths(12))->delete();
        $journal = JournalActivite::query()->where('created_at', '<', now()->subYears(3))->delete();

        $aEffacer = Membre::query()
            ->whereNull('anonymise_le')
            ->where(fn ($requete) => $requete
                ->where(fn ($q) => $q
                    ->whereIn('statut', [StatutMembre::Rejete->value, StatutMembre::Expire->value])
                    ->where('updated_at', '<', now()->subMonths(6)))
                ->orWhere(fn ($q) => $q
                    ->where('statut', StatutMembre::Radie->value)
                    ->where('updated_at', '<', now()->subDays(30))))
            ->get();

        foreach ($aEffacer as $membre) {
            $donnees->anonymiser($membre);
        }

        $this->info("Connexions supprimées : {$connexions}. Lignes de journal supprimées : {$journal}. Fiches anonymisées : {$aEffacer->count()}.");

        return self::SUCCESS;
    }
}
