<?php

namespace App\Console\Commands;

use App\Services\AssiduiteService;
use Illuminate\Console\Command;

class RoutineDahiras extends Command
{
    protected $signature = 'dahiras:routine';

    protected $description = 'Rappelle les dahiras du jour et signale les membres absents 3 dahiras de suite.';

    public function handle(AssiduiteService $assiduite): int
    {
        $bilan = $assiduite->routineDuMatin();
        $this->info($bilan['rappels'].' rappel(s) et '.$bilan['alertes'].' alerte(s) envoyés.');

        return self::SUCCESS;
    }
}
