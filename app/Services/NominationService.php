<?php

namespace App\Services;

use App\Enums\StatutMembre;
use App\Models\Cellule;
use App\Models\Commission;
use App\Models\Instance;
use App\Models\Nomination;
use App\Models\Poste;
use App\Models\User;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Les nominations : le super administrateur ou le Jewrine national nomme aux postes ; le Jewrine national,
 * les Jewrine de commission et les convocateurs du Comité directeur ne sont nommés que par le super administrateur.
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

        return ! in_array($poste->code, Poste::RESERVES_AU_SUPER_ADMINISTRATEUR, true) || CasquettesService::estSuperAdministrateur($auteur);
    }

    /**
     * Nomme une personne à un poste. Sur un poste à titulaire unique, la nomination précédente
     * (dans la même cellule pour un Jewrine de cellule) prend fin : « remplacé(e) par … ».
     */
    public function nommer(User $personne, Poste $poste, ?Cellule $cellule, User $auteur, ?Carbon $debut = null, ?Commission $commission = null, ?Instance $comite = null): Nomination
    {
        if (! self::peutNommer($auteur, $poste)) {
            throw new DomainException(in_array($poste->code, Poste::RESERVES_AU_SUPER_ADMINISTRATEUR, true)
                ? 'Seul le super administrateur nomme à ce poste ('.$poste->nom.').'
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

        if ($poste->estDeCommission() && $commission === null) {
            throw new DomainException('Choisissez la commission de ce '.$poste->nom.'.');
        }

        if ($poste->estDeComite() && $comite === null) {
            throw new DomainException('Choisissez le comité de ce '.$poste->nom.'.');
        }

        $cellule = $poste->estDeCellule() ? $cellule : null;
        $commission = $poste->estDeCommission() ? $commission : null;
        $comite = $poste->estDeComite() ? $comite : null;

        $dejaNomme = Nomination::query()
            ->where('user_id', $personne->id)
            ->where('poste_id', $poste->id)
            ->where('cellule_id', $cellule?->id)
            ->where('commission_id', $commission?->id)
            ->where('instance_id', $comite?->id)
            ->exists();

        if ($dejaNomme) {
            throw new DomainException($membre->prenom.' '.$membre->nom.' occupe déjà ce poste.');
        }

        $nomination = DB::transaction(function () use ($personne, $poste, $cellule, $commission, $comite, $auteur, $debut, $membre): Nomination {
            if ($poste->titulaire_unique) {
                $precedentes = Nomination::query()->where('poste_id', $poste->id)
                    ->where('cellule_id', $cellule?->id)->where('commission_id', $commission?->id)->where('instance_id', $comite?->id)->get();

                foreach ($precedentes as $precedente) {
                    $this->clore($precedente, 'Remplacé(e) par '.$membre->prenom.' '.$membre->nom, $auteur);
                }
            }

            return Nomination::query()->create([
                'user_id' => $personne->id,
                'poste_id' => $poste->id,
                'cellule_id' => $cellule?->id,
                'commission_id' => $commission?->id,
                'instance_id' => $comite?->id,
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
            throw new DomainException('Seuls le super administrateur et le Jewrine national mettent fin à une nomination (pour le Jewrine national, les Jewrine de commission et les convocateurs : seul le super administrateur).');
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
