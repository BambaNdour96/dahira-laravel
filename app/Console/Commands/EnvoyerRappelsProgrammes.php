<?php

namespace App\Console\Commands;

use App\Services\RappelService;
use Illuminate\Console\Command;

class EnvoyerRappelsProgrammes extends Command
{
    protected $signature = 'programmes:rappeler';

    protected $description = 'Envoie les rappels du jour aux membres (programmes, collectes et jour J).';

    public function handle(RappelService $rappels): int
    {
        $nombre = $rappels->envoyer();
        $this->info($nombre.' rappel(s) envoyé(s).');

        return self::SUCCESS;
    }
}
