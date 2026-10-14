<?php

namespace App\Services;

use App\Enums\StatutMembre;
use App\Models\Cellule;
use App\Models\Nomination;
use App\Models\Poste;
use App\Models\User;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Les nominations : le super administrateur ou le Jewrine national nomme aux postes ;
 * le poste de Jewrine national n'est attribué que par le super administrateur.
 * Une nomination prend fin avec un motif ; elle n'est jamais effacée.
 */
final class NominationService
{
    public function __construct(private readonly CasquettesService $casquettes) {}

    /** Peut-il nommer à ce poste, et y mettre fin ? */
    public static function peutNommer(User $auteur, Poste $poste): bool
    {
        if (! $auteur->can('nominations.gerer')) {
            return false;
        }

        return $poste->code !== Poste::JEWRINE_NATIONAL || CasquettesService::estSuperAdministrateur($auteur);
    }

    /**
     * Nomme une personne à un poste. Sur un poste à titulaire unique, la nomination précédente
     * (dans la même cellule pour un Jewrine de cellule) prend fin : « remplacé(e) par … ».
     */
    public function nommer(User $personne, Poste $poste, ?Cellule $cellule, User $auteur, ?Carbon $debut = null): Nomination
    {
        if (! self::peutNommer($auteur, $poste)) {
            throw new DomainException($poste->code === Poste::JEWRINE_NATIONAL
                ? 'Seul le super administrateur nomme le Jewrine national.'
                : 'Seuls le super administrateur et le Jewrine national nomment aux postes.');
        }

        if (! $poste->actif) {
            throw new DomainException('Ce poste est archivé.');
        }

        $membre = $personne->membre;

        if ($membre === null || $membre->statut !== StatutMembre::Valide || ! $personne->actif) {
            throw new DomainException('Seul un membre validé, avec un compte actif, peut être nommé.');
        }

        if ($poste->estDeCellule() && ($cellule === null || $cellule->estArchive())) {
            throw new DomainException('Choisissez la cellule de ce '.$poste->nom.'.');
        }

        $cellule = $poste->estDeCellule() ? $cellule : null;

        $dejaNomme = Nomination::query()
            ->where('user_id', $personne->id)
            ->where('poste_id', $poste->id)
            ->where('cellule_id', $cellule?->id)
            ->exists();

        if ($dejaNomme) {
            throw new DomainException($membre->prenom.' '.$membre->nom.' occupe déjà ce poste.');
        }

        $nomination = DB::transaction(function () use ($personne, $poste, $cellule, $auteur, $debut, $membre): Nomination {
            if ($poste->titulaire_unique) {
                $precedentes = Nomination::query()->where('poste_id', $poste->id)->where('cellule_id', $cellule?->id)->get();

                foreach ($precedentes as $precedente) {
                    $this->clore($precedente, 'Remplacé(e) par '.$membre->prenom.' '.$membre->nom, $auteur);
                }
            }

            return Nomination::query()->create([
                'user_id' => $personne->id,
                'poste_id' => $poste->id,
                'cellule_id' => $cellule?->id,
                'debut_le' => ($debut ?? now())->toDateString(),
                'designe_par' => $auteur->id,
            ]);
        });

        $this->casquettes->synchroniser($personne);

        return $nomination;
    }

    /** Met fin à une nomination, avec un motif ; la personne perd les droits de ce poste, pas les autres. */
    public function mettreFin(Nomination $nomination, string $motif, User $auteur): void
    {
        if (! self::peutNommer($auteur, $nomination->poste)) {
            throw new DomainException('Seuls le super administrateur et le Jewrine national mettent fin à une nomination (le Jewrine national : seul le super administrateur).');
        }

        if ($nomination->estTerminee()) {
            throw new DomainException('Cette nomination a déjà pris fin.');
        }

        if (trim($motif) === '') {
            throw new DomainException('Indiquez le motif.');
        }

        $this->clore($nomination, $motif, $auteur);
    }

    /**
     * Toutes ses nominations prennent fin : la personne quitte la Daara (radiation) ou son compte est anonymisé.
     * Décision de la plateforme, sans contrôle de droits : la cause a déjà été validée.
     */
    public function terminerToutes(User $personne, string $motif, ?User $auteur = null): void
    {
        foreach (Nomination::query()->where('user_id', $personne->id)->get() as $nomination) {
            $this->clore($nomination, $motif, $auteur);
        }
    }

    private function clore(Nomination $nomination, string $motif, ?User $auteur): void
    {
        $nomination->forceFill([
            'fin_le' => now(),
            'fin_par' => $auteur?->id,
            'motif_fin' => mb_substr(trim($motif), 0, 255),
        ])->save();

        $this->casquettes->synchroniser($nomination->user);
    }
}
