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
    /** @return array<int, Evenement> Les événements dont ce contrôleur peut scanner les cartes maintenant. */
    public function evenementsOuvertsPour(User $controleur): array
    {
        return Evenement::query()
            ->where('debut', '<=', now()->addHours(Evenement::MARGE_HEURES))
            ->where('debut', '>=', now()->subDays(2))
            ->when(! $controleur->hasRole('super_administrateur'), fn ($requete) => $requete
                ->whereHas('controleurs', fn ($controleurs) => $controleurs->where('users.id', $controleur->id)))
            ->orderBy('debut')
            ->get()
            ->filter(fn (Evenement $evenement): bool => $evenement->scanOuvert())
            ->values()
            ->all();
    }

    /** Enregistre la présence ; renvoie faux si le membre était déjà marqué présent. */
    public function marquer(Evenement $evenement, Membre $membre, User $controleur): bool
    {
        $autorise = $controleur->hasRole('super_administrateur')
            || $evenement->controleurs()->where('users.id', $controleur->id)->exists();

        if (! $autorise) {
            throw new DomainException('Vous n\'êtes pas contrôleur de cet événement.');
        }

        if (! $evenement->scanOuvert()) {
            throw new DomainException('Le contrôle des présences n\'est pas ouvert pour cet événement.');
        }

        if ($membre->statut !== StatutMembre::Valide) {
            throw new DomainException('Cette carte n\'appartient pas à un membre validé.');
        }

        $presence = Presence::query()->firstOrCreate(
            ['evenement_id' => $evenement->id, 'membre_id' => $membre->id],
            ['enregistree_par' => $controleur->id],
        );

        return $presence->wasRecentlyCreated;
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
