<?php

namespace App\Observers;

use App\Enums\NiveauEtudes;
use App\Enums\SituationProfessionnelle;
use App\Models\Membre;

/**
 * Garde des fiches cohérentes : les champs sans objet sont vidés à l'enregistrement.
 * « Sans emploi » garde la profession : on peut être sans emploi et avoir un métier.
 */
class CoherenceProfilObserver
{
    public function saving(Membre $membre): void
    {
        if ($membre->niveau_etudes !== NiveauEtudes::Autre) {
            $membre->niveau_etudes_autre = null;
        }

        if ($membre->niveau_etudes === NiveauEtudes::Aucun) {
            $membre->diplome = null;
            $membre->domaine_etudes = null;
        }

        if ($membre->situation_professionnelle === SituationProfessionnelle::Retraite) {
            $membre->profession = null;
            $membre->secteur_activite = null;
        }

        if ($membre->situation_professionnelle === SituationProfessionnelle::EleveEtudiant) {
            $membre->profession = null;
        }
    }
}
