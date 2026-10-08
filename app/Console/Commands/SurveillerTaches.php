<?php

namespace App\Console\Commands;

use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\User;
use App\Support\Taches;
use Illuminate\Console\Command;

/**
 * Chaque matin : prévient le super administrateur des tâches en attente depuis plus de 3 jours.
 */
class SurveillerTaches extends Command
{
    protected $signature = 'taches:surveiller';

    protected $description = 'Alerte le super administrateur des tâches en attente depuis plus de 3 jours';

    public function handle(): int
    {
        $limite = now()->subDays(3);

        $demandes = Membre::query()
            ->where('statut', StatutMembre::EnAttente->value)
            ->where('updated_at', '<', $limite)
            ->count();

        $paiements = Paiement::query()
            ->where('statut', StatutPaiement::Declare->value)
            ->where('declare_le', '<', $limite)
            ->count();

        if ($demandes + $paiements === 0) {
            $this->info('Aucune tâche en retard.');

            return self::SUCCESS;
        }

        Taches::envoyer(
            User::query()->whereHas('roles', fn ($roles) => $roles->where('name', 'super_administrateur'))->get(),
            'Des tâches attendent depuis plus de 3 jours',
            $demandes.' demande(s) d\'adhésion et '.$paiements.' paiement(s) attendent une réponse.',
            url('/admin'),
        );

        $this->info("Tâches en retard : {$demandes} demande(s), {$paiements} paiement(s).");

        return self::SUCCESS;
    }
}
