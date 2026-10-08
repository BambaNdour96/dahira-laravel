<?php

namespace App\Observers;

use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Models\Membre;
use App\Models\Paiement;
use App\Support\Taches;
use Illuminate\Database\Eloquent\Model;

/**
 * Crée les tâches du bureau : nouvelles demandes, fiches renvoyées, paiements déclarés.
 */
class TachesObserver
{
    public function created(Model $modele): void
    {
        if ($modele instanceof Membre && $modele->statut === StatutMembre::EnAttente) {
            Taches::demandeAExaminer($modele, 'Nouvelle demande d\'adhésion');
        }

        if ($modele instanceof Paiement && $modele->statut === StatutPaiement::Declare) {
            Taches::paiementAVerifier($modele);
        }
    }

    public function updated(Model $modele): void
    {
        if (! $modele instanceof Membre || ! $modele->wasChanged('statut') || $modele->statut !== StatutMembre::EnAttente) {
            return;
        }

        $titre = $modele->getOriginal('statut') === StatutMembre::Expire
            ? 'Demande renvoyée après expiration'
            : 'Fiche corrigée, à réexaminer';

        Taches::demandeAExaminer($modele, $titre);
    }
}
