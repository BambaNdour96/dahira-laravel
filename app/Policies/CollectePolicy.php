<?php

namespace App\Policies;

use App\Enums\StatutCollecte;
use App\Models\Collecte;
use App\Models\Engagement;
use App\Models\User;

/**
 * Droits sur les collectes, du super administrateur au Jewrine finance :
 * - le super administrateur crée et paramètre tout ;
 * - le Jewrine national clôture et rouvre ;
 * - la direction (Jewrine national, Secrétaire général) attribue des montants ;
 * - le bureau entier (avec le Jewrine finance) modifie un engagement, avec motif et trace ;
 * - le Jewrine finance confirme les versements (voir PaiementPolicy).
 */
class CollectePolicy
{
    public const BUREAU = ['super_administrateur', 'jewrine_national', 'secretaire_general', 'tresorier'];

    /** Voient les collectes : le bureau, la commission Suivi et Évaluation (en lecture) et les Jewrine de cellule (leur cellule). */
    public const LECTURE = [...self::BUREAU, 'suivi_evaluation', 'responsable_section'];

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(self::LECTURE);
    }

    public function view(User $user, Collecte $collecte): bool
    {
        return $user->hasAnyRole(self::LECTURE);
    }

    /** Un Jewrine de cellule ne voit que sa cellule ; le reste du bureau et Suivi et Évaluation voient tout. */
    public static function limiteeACellule(User $user): bool
    {
        return ! $user->hasAnyRole([...self::BUREAU, 'suivi_evaluation']);
    }

    /** Relancer un engagement : Jewrine finance et super administrateur, ou Jewrine de la cellule du membre. */
    public static function peutRelancer(User $user, Engagement $engagement): bool
    {
        if ($user->hasAnyRole(['super_administrateur', 'tresorier'])) {
            return true;
        }

        return $user->hasRole('responsable_section') && $engagement->membre->cellule->responsable_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_administrateur');
    }

    public function update(User $user, Collecte $collecte): bool
    {
        return $user->hasRole('super_administrateur') && $collecte->statut !== StatutCollecte::Annulee;
    }

    /** Rien ne se supprime : un programme s'annule (voir annuler). */
    public function delete(User $user, Collecte $collecte): bool
    {
        return false;
    }

    public function annuler(User $user, Collecte $collecte): bool
    {
        return $user->hasRole('super_administrateur') && $collecte->statut !== StatutCollecte::Annulee;
    }

    public function cloturer(User $user, Collecte $collecte): bool
    {
        return $user->hasAnyRole(['super_administrateur', 'jewrine_national']);
    }

    public function attribuer(User $user, Collecte $collecte): bool
    {
        return $user->hasAnyRole(['super_administrateur', 'jewrine_national', 'secretaire_general']);
    }

    public function modifierEngagement(User $user, Collecte $collecte): bool
    {
        return $user->hasAnyRole(self::BUREAU);
    }
}
