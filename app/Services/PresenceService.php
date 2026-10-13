<?php

namespace App\Services;

use App\Enums\StatutMembre;
use App\Models\Carte;
use App\Models\Evenement;
use App\Models\Membre;
use App\Models\Presence;
use App\Models\User;
use DomainException;

/**
 * Enregistrement des présences au scan des cartes, par les contrôleurs désignés.
 */
final class PresenceService
{
    /** @return array<int, Evenement> Les rendez-vous dont cette personne peut pointer les présences maintenant. */
    public function evenementsOuvertsPour(User $controleur): array
    {
        return app(PointageService::class)->rendezVousDuMoment($controleur)->all();
    }

    /** Enregistre la présence au scan de la carte ; renvoie faux si le membre était déjà marqué présent. */
    public function marquer(Evenement $evenement, Membre $membre, User $controleur): bool
    {
        if (! $evenement->scanOuvert()) {
            throw new DomainException('Le contrôle des présences n\'est pas ouvert pour cet événement.');
        }

        $deja = Presence::query()
            ->where('evenement_id', $evenement->id)
            ->where('membre_id', $membre->id)
            ->where('statut', 'present')
            ->exists();

        if ($deja) {
            return false;
        }

        app(PointageService::class)->pointer($evenement, $membre, $controleur);

        return true;
    }

    /**
     * Ce qu'il faut afficher sur la page de vérification d'une carte, pour un contrôleur connecté.
     *
     * @return array{membre: Membre|null, evenements: array<int, array{evenement: Evenement, present: bool}>}
     */
    public static function contexteScan(mixed $jeton, mixed $utilisateur): array
    {
        $vide = ['membre' => null, 'evenements' => []];

        if (! is_string($jeton) || ! $utilisateur instanceof User) {
            return $vide;
        }

        $carte = Carte::query()->where('jeton', $jeton)->where('statut', 'active')->with('membre')->first();

        if ($carte === null || $carte->membre->statut !== StatutMembre::Valide) {
            return $vide;
        }

        $evenements = array_map(fn (Evenement $evenement): array => [
            'evenement' => $evenement,
            'present' => Presence::query()->where('evenement_id', $evenement->id)->where('membre_id', $carte->membre_id)->exists(),
        ], app(self::class)->evenementsOuvertsPour($utilisateur));

        return ['membre' => $carte->membre, 'evenements' => $evenements];
    }
}
