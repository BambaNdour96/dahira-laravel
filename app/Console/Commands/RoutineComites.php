<?php

namespace App\Console\Commands;

use App\Services\ComiteService;
use Illuminate\Console\Command;

class RoutineComites extends Command
{
    protected $signature = 'comites:routine';

    protected $description = 'Rappelle les actions à échéance et signale les retards aux présidents des comités.';

    public function handle(ComiteService $comites): int
    {
        $bilan = $comites->routineDuMatin();
        $this->info($bilan['rappels'].' rappel(s), '.$bilan['retards'].' retard(s) signalé(s).');

        return self::SUCCESS;
    }
}
