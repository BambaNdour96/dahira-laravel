<?php

namespace App\Console\Commands;

use App\Services\FamilleService;
use Illuminate\Console\Command;

class RoutineFamilles extends Command
{
    protected $signature = 'familles:routine';

    protected $description = 'Propose de donner son propre compte à chaque proche qui atteint 18 ans.';

    public function handle(FamilleService $familles): int
    {
        $this->info($familles->proposerComptesAuxMajeurs().' proposition(s) envoyée(s).');

        return self::SUCCESS;
    }
}
