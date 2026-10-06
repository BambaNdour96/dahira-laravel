<?php

namespace App\Console\Commands;

use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Models\Membre;
use App\Services\StatutMembreService;
use Illuminate\Console\Command;

/**
 * Les demandes acceptées et non payées expirent après le délai prévu,
 * sauf si un paiement est en cours de vérification.
 */
class ExpirerDemandes extends Command
{
    protected $signature = 'membres:expirer';

    protected $description = 'Fait expirer les demandes acceptées dont les frais de carte ne sont pas réglés';

    public function handle(StatutMembreService $statuts): int
    {
        $membres = Membre::query()
            ->where('statut', StatutMembre::Accepte->value)
            ->where('accepte_le', '<=', now()->subDays(Membre::DELAI_PAIEMENT_JOURS))
            ->whereDoesntHave('paiements', fn ($requete) => $requete->where('statut', StatutPaiement::Declare->value))
            ->get();

        foreach ($membres as $membre) {
            $statuts->changer($membre, StatutMembre::Expire, null, 'Délai de paiement des frais de carte dépassé.');
        }

        $this->info($membres->count().' demande(s) expirée(s).');

        return self::SUCCESS;
    }
}
