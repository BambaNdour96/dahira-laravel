<?php

namespace App\Console\Commands;

use App\Services\RepresentantFinanceService;
use Illuminate\Console\Command;

class RoutineFinance extends Command
{
    protected $signature = 'finance:routine';

    protected $description = 'Alerte le Jewrine finance national : paiements en attente depuis 48 heures et caisses de représentants à remettre.';

    public function handle(RepresentantFinanceService $representants): int
    {
        $bilan = $representants->routineDuMatin();
        $this->info($bilan['en_attente'].' paiement(s) en attente depuis 48 h, '.$bilan['caisses'].' caisse(s) à remettre.');

        return self::SUCCESS;
    }
}
