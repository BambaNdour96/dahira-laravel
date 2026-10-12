<?php

namespace App\Services;

use App\Models\Collecte;
use App\Models\Evenement;

/**
 * Le « jour J » d'un programme : le rendez-vous lui-même (date, lieu, scan des cartes),
 * enregistré comme un événement relié à la collecte.
 */
final class JourJService
{
    /**
     * Retire du formulaire les champs du jour J (ce ne sont pas des colonnes de la collecte).
     *
     * @param  array<string, mixed>  $data
     * @return array{avec: bool, debut: mixed, fin: mixed, lieu: string|null}
     */
    public static function extraire(array &$data): array
    {
        $jourJ = [
            'avec' => (bool) ($data['avec_jour_j'] ?? false),
            'debut' => $data['jour_j_debut'] ?? null,
            'fin' => $data['jour_j_fin'] ?? null,
            'lieu' => isset($data['jour_j_lieu']) ? (string) $data['jour_j_lieu'] : null,
        ];

        unset($data['avec_jour_j'], $data['jour_j_debut'], $data['jour_j_fin'], $data['jour_j_lieu']);

        return $jourJ;
    }

    /**
     * Remplit le formulaire avec le jour J existant.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function remplir(Collecte $collecte, array $data): array
    {
        $evenement = $collecte->evenement;

        return [
            ...$data,
            'avec_jour_j' => $evenement !== null,
            'jour_j_debut' => $evenement?->debut,
            'jour_j_fin' => $evenement?->fin,
            'jour_j_lieu' => $evenement?->lieu,
        ];
    }

    /**
     * Crée, met à jour ou détache le jour J. Un jour J détaché est conservé (avec ses présences).
     *
     * @param  array{avec: bool, debut: mixed, fin: mixed, lieu: string|null}  $jourJ
     */
    public function synchroniser(Collecte $collecte, array $jourJ, ?int $auteurId): void
    {
        if (! $jourJ['avec']) {
            if ($collecte->evenement_id !== null) {
                $collecte->update(['evenement_id' => null]);
            }

            return;
        }

        $valeurs = [
            'titre' => $collecte->titre,
            'description' => $collecte->description,
            'lieu' => (string) $jourJ['lieu'],
            'debut' => $jourJ['debut'],
            'fin' => $jourJ['fin'],
            'cellule_id' => $collecte->cellule_id,
        ];

        $evenement = $collecte->evenement;

        if ($evenement !== null) {
            $evenement->update($valeurs);

            return;
        }

        $evenement = Evenement::query()->create([...$valeurs, 'auteur_id' => $auteurId]);
        $collecte->update(['evenement_id' => $evenement->id]);
    }
}
