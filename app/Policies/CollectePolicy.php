<?php

namespace App\Policies;

use App\Enums\StatutCollecte;
use App\Models\Collecte;
use App\Models\Engagement;
use App\Models\User;
use App\Services\CasquettesService;

/**
 * Droits sur les programmes (voir App\Support\Capacites) :
 * - « programmes.parametrer » : créer, paramétrer, annuler (super administrateur) ;
 * - « programmes.cloturer » : clôturer et rouvrir ; « programmes.attribuer » : attribuer des montants ;
 * - « programmes.piloter » : modifier un engagement, avec motif et trace ;
 * - « programmes.voir » : toute la Daara, ou sa cellule pour le Jewrine de cellule.
 * Les versements se confirment selon PaiementPolicy.
 */
class CollectePolicy
{
    public function viewAny(User $user): bool
    {
        return app(CasquettesService::class)->peutQuelquePart($user, 'programmes.voir');
    }

    public function view(User $user, Collecte $collecte): bool
    {
        return app(CasquettesService::class)->peutQuelquePart($user, 'programmes.voir');
    }

    /** Un Jewrine de cellule ne voit que sa cellule ; ceux qui ont « programmes.voir » voient tout. */
    public static function limiteeACellule(User $user): bool
    {
        return ! $user->can('programmes.voir');
    }

    /** Relancer un engagement : pour toute la Daara, ou le Jewrine de la cellule du membre. */
    public static function peutRelancer(User $user, Engagement $engagement): bool
    {
        return app(CasquettesService::class)->peut($user, 'relances.envoyer', $engagement->membre->cellule_id);
    }

    public function create(User $user): bool
    {
        return $user->can('programmes.parametrer');
    }

    public function update(User $user, Collecte $collecte): bool
    {
        return $user->can('programmes.parametrer') && $collecte->statut !== StatutCollecte::Annulee;
    }

    /** Rien ne se supprime : un programme s'annule (voir annuler). */
    public function delete(User $user, Collecte $collecte): bool
    {
        return false;
    }

    public function annuler(User $user, Collecte $collecte): bool
    {
        return $user->can('programmes.parametrer') && $collecte->statut !== StatutCollecte::Annulee;
    }

    public function cloturer(User $user, Collecte $collecte): bool
    {
        return $user->can('programmes.cloturer');
    }

    public function attribuer(User $user, Collecte $collecte): bool
    {
        return $user->can('programmes.attribuer');
    }

    public function modifierEngagement(User $user, Collecte $collecte): bool
    {
        return $user->can('programmes.piloter');
    }
}
