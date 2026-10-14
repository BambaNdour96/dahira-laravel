<?php

namespace App\Services;

use App\Enums\StatutMembre;
use App\Models\Membre;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Règle 4.1 : seules les transitions prévues sont autorisées,
 * avec un motif quand il est exigé. La validation émet la carte.
 */
final class StatutMembreService
{
    public function __construct(
        private readonly MatriculeService $matricules,
        private readonly CarteService $cartes,
    ) {}

    public function changer(Membre $membre, StatutMembre $nouveau, ?User $auteur, ?string $motif = null): void
    {
        if (! $membre->statut->peutPasserA($nouveau)) {
            throw new DomainException(sprintf(
                'Transition interdite : %s vers %s.',
                $membre->statut->getLabel(),
                $nouveau->getLabel(),
            ));
        }

        if ($nouveau->exigeMotif() && blank($motif)) {
            throw new DomainException('Un motif est obligatoire pour ce changement de statut.');
        }

        DB::transaction(function () use ($membre, $nouveau, $auteur, $motif): void {
            $membre->statut = $nouveau;
            $membre->motif_statut = $motif;

            if ($nouveau === StatutMembre::Accepte) {
                $membre->accepte_le = now();
            }

            if ($nouveau === StatutMembre::Valide && $membre->valide_le === null) {
                $membre->valide_le = now();
                $membre->valide_par = $auteur?->id;

                if ($membre->date_adhesion === null) {
                    $membre->date_adhesion = now();
                }
            }

            $membre->save();

            if ($nouveau === StatutMembre::Valide) {
                $this->matricules->attribuer($membre);
                $this->cartes->emettre($membre);
            }

            // Un membre radié quitte aussi tous ses postes : ses nominations prennent fin, l'historique reste.
            if ($nouveau === StatutMembre::Radie) {
                app(CommissionService::class)->terminerToutes($membre, 'Membre radié');

                if ($membre->user !== null) {
                    app(NominationService::class)->terminerToutes($membre->user, 'Membre radié', $auteur);
                }
            }
        });
    }
}
