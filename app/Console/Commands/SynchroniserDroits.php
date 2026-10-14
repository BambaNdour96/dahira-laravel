<?php

namespace App\Console\Commands;

use App\Support\Capacites;
use Illuminate\Console\Command;

/**
 * Reporte la matrice des droits (App\Support\Capacites) dans la base : casquettes et capacités.
 * À lancer après chaque modification de la matrice, et à chaque mise en ligne.
 */
class SynchroniserDroits extends Command
{
    protected $signature = 'droits:synchroniser';

    protected $description = 'Reporte la matrice des droits dans la base (casquettes et capacités)';

    public function handle(): int
    {
        Capacites::synchroniser();

        $this->info(count(Capacites::toutes()).' capacités réparties entre '.count(Capacites::CASQUETTES).' casquettes.');

        return self::SUCCESS;
    }
}
