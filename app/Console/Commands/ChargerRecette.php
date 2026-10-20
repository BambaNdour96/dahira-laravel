<?php

namespace App\Console\Commands;

use Database\Seeders\RecetteSeeder;
use Illuminate\Console\Command;
use Throwable;

class ChargerRecette extends Command
{
    protected $signature = 'recette:charger {--fraiche : Vide la base et la recrée avant de charger (sauvegardez-la d\'abord)}';

    protected $description = 'Charge les données de recette (comptes de toutes les casquettes, famille, paiements, programme, CDGMT, renouvellement). Jamais en production.';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Les données de recette ne se chargent jamais en production.');

            return self::FAILURE;
        }

        if ($this->option('fraiche')) {
            $this->call('migrate:fresh', ['--force' => true]);
        }

        try {
            $this->call('db:seed', ['--class' => RecetteSeeder::class, '--force' => true]);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Données de recette chargées. Comptes et mots de passe : docs/guide/comptes-de-recette.md');

        // Avant le mois de démarrage des cotisations, personne n'est en retard : la recette ne montrerait aucun retard.
        if ((string) config('dahira.cotisations_debut') >= now()->format('Y-m')) {
            $this->warn('Les cotisations démarrent en '.config('dahira.cotisations_debut').' (COTISATIONS_DEBUT du .env) : aucun membre n\'apparaît en retard. '
                .'Pour voir des retards en recette, mettez par exemple COTISATIONS_DEBUT='.now()->subYear()->format('Y-m').', puis php artisan config:clear.');
        }

        return self::SUCCESS;
    }
}
