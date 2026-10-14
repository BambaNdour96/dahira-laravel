<?php

namespace App\Services;

use App\Models\JournalActivite;
use App\Models\User;
use App\Support\AffichageMotDePasse;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Les comptes de connexion, gérés par le super administrateur : activation et mot de passe provisoire.
 * Les droits ne se règlent pas ici, mais par les nominations (voir NominationService).
 */
final class CompteService
{
    /** Active ou désactive un compte ; un compte désactivé ne peut plus se connecter. */
    public function changerEtat(User $user, bool $actif): void
    {
        if (CasquettesService::estSuperAdministrateur($user)) {
            throw new DomainException('Le compte d\'un super administrateur ne se modifie pas ici.');
        }

        if ($actif === (bool) $user->actif) {
            return;
        }

        $user->update(['actif' => $actif]);
        JournalActivite::enregistrer($user, 'modifie', [
            'apres' => ['compte' => $actif ? 'réactivé' : 'désactivé'],
        ]);
    }

    /** Génère un mot de passe provisoire, à transmettre à la personne. */
    public function reinitialiserMotDePasse(User $user): string
    {
        $motDePasse = AffichageMotDePasse::generer();
        $user->forceFill(['password' => $motDePasse, 'doit_changer_mot_de_passe' => true])->save();

        // Le compte est débloqué, et toutes ses connexions ouvertes sont fermées.
        RateLimiter::clear('blocage-compte:'.$user->telephone);
        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        JournalActivite::enregistrer($user, 'modifie', ['apres' => ['mot_de_passe' => 'réinitialisé']]);

        return $motDePasse;
    }
}
