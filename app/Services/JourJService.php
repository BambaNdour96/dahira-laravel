<?php

namespace App\Services;

use App\Models\Collecte;
use App\Models\Evenement;

/**
 * Le « jour J » d'un événement : le rendez-vous lui-même, avec le scan des cartes à l'entrée. Il reprend les dates et
 * le lieu saisis une fois sur l'événement, et s'enregistre comme un événement de pointage relié à la collecte.
 */
final class JourJService
{
    /**
     * Le formulaire de l'événement, rempli avec ce qui ne se stocke pas sur lui : le jour J, le président, les commissions.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function remplir(Collecte $collecte, array $data): array
    {
        return [
            ...$data,
            'avec_jour_j' => $collecte->evenement_id !== null,
            'president_id' => $collecte->comite?->president_id,
            'commissions' => $collecte->commissionsChoisies()->whereNull('retiree_le')->get()
                ->map(fn ($choix): array => ['commission_id' => $choix->commission_id, 'budget' => $choix->budget])->values()->all(),
        ];
    }

    /** Crée, met à jour ou détache le jour J. Un jour J détaché est conservé (avec ses présences). */
    public function synchroniser(Collecte $collecte, bool $avec, ?int $auteurId): void
    {
        if (! $avec || $collecte->debut === null) {
            if ($collecte->evenement_id !== null) {
                $collecte->update(['evenement_id' => null]);
            }

            return;
        }

        $valeurs = [
            'titre' => $collecte->titre,
            'description' => $collecte->description,
            'lieu' => (string) $collecte->lieu,
            'debut' => $collecte->debut,
            'fin' => $collecte->fin,
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
