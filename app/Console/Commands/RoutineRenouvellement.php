<?php

namespace App\Console\Commands;

use App\Services\RenouvellementService;
use Illuminate\Console\Command;

class RoutineRenouvellement extends Command
{
    protected $signature = 'renouvellement:routine';

    protected $description = 'Relance les membres qui n\'ont pas renouvelé (30, 7 et 1 jour avant la date limite), puis clôt la campagne à la date limite.';

    public function handle(RenouvellementService $renouvellements): int
    {
        $bilan = $renouvellements->routine();
        $this->info($bilan['relances'].' relance(s), '.$bilan['non_renouveles'].' membre(s) non renouvelé(s).');

        return self::SUCCESS;
    }
}
