<?php

namespace App\Console\Commands;

use App\Services\AssiduiteService;
use Illuminate\Console\Command;

class RoutineSeances extends Command
{
    protected $signature = 'seances:routine';

    protected $description = 'Rappelle les séances de cellule du jour et signale les membres absents 3 séances de suite.';

    public function handle(AssiduiteService $assiduite): int
    {
        $bilan = $assiduite->routineDuMatin();
        $this->info($bilan['rappels'].' rappel(s) et '.$bilan['alertes'].' alerte(s) envoyés.');

        return self::SUCCESS;
    }
}
