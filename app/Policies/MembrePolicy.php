<?php

namespace App\Policies;

use App\Enums\StatutMembre;
use App\Models\Membre;
use App\Models\User;
use App\Services\CasquettesService;

/**
 * Le registre des membres. Une capacité vaut pour toute la Daara ; le Jewrine de cellule
 * l'a pour sa cellule seulement (voir App\Support\Capacites).
 */
class MembrePolicy
{
    public function __construct(private readonly CasquettesService $casquettes) {}

    public function viewAny(User $user): bool
    {
        return $this->casquettes->peutQuelquePart($user, 'membres.voir');
    }

    public function view(User $user, Membre $membre): bool
    {
        return $this->casquettes->peut($user, 'membres.voir', $membre->cellule_id);
    }

    public function create(User $user): bool
    {
        return $this->casquettes->peutQuelquePart($user, 'membres.inscrire');
    }

    public function update(User $user, Membre $membre): bool
    {
        return $this->casquettes->peut($user, 'membres.modifier', $membre->cellule_id);
    }

    /** Rien ne se supprime : une fiche rejetée est anonymisée au terme de sa durée de conservation. */
    public function delete(User $user, Membre $membre): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    /** Un membre voit sa propre photo ; le bureau voit celles qu'il a le droit de consulter. */
    public function voirPhoto(User $user, Membre $membre): bool
    {
        return $membre->user_id === $user->id || $this->view($user, $membre);
    }

    /** Révoquer une carte (perte, vol). */
    public function revoquerCarte(User $user, Membre $membre): bool
    {
        return $user->can('cartes.revoquer');
    }

    /**
     * Pour toute la Daara : tout changement de statut, y compris valider sans paiement.
     * Le Jewrine de cellule accepte, fait corriger ou rejette les demandes de sa cellule.
     */
    public function changerStatut(User $user, Membre $membre, StatutMembre $cible): bool
    {
        // Personne ne modifie son propre statut, même un administrateur.
        if ($membre->user_id !== null && $membre->user_id === $user->id) {
            return false;
        }

        // Le statut d'un responsable ne change que par le super administrateur.
        if ($membre->user !== null && $this->casquettes->estDuBureau($membre->user) && ! $user->can('membres.statut_responsables')) {
            return false;
        }

        // Suspendre un membre, ou réactiver un membre suspendu : décision de direction.
        if ($cible === StatutMembre::Suspendu || $membre->statut === StatutMembre::Suspendu) {
            return $user->can('membres.suspendre');
        }

        if ($user->can('membres.valider')) {
            return true;
        }

        return $this->casquettes->peut($user, 'membres.valider', $membre->cellule_id)
            && $membre->statut === StatutMembre::EnAttente
            && in_array($cible, [StatutMembre::Accepte, StatutMembre::ACorriger, StatutMembre::Rejete], true);
    }
}
