<?php

namespace App\Services;

use App\Models\Collecte;
use App\Models\Projet;
use App\Models\ReaffectationFonds;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Support\Numerotation;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Les réaffectations de fonds : le Jewrine national décide (le solde du Magal vers un projet, par exemple), la Finance
 * enregistre, jamais la même personne. L'argent ne change pas de caisse, il change d'affectation ; le fonds d'origine
 * doit avoir de quoi. Une réaffectation décidée s'annule tant qu'elle n'est pas enregistrée ; enregistrée, elle se
 * corrige par une réaffectation inverse.
 */
final class ReaffectationService
{
    public function __construct(private readonly FinanceRapportService $rapports) {}

    /**
     * Décide une réaffectation.
     *
     * Les champs : montant, motif, origine et destination (programme, projet ou fonctionnement) avec leur programme ou
     * leur projet.
     *
     * @param  array<string, mixed>  $donnees
     */
    public function decider(array $donnees, User $auteur): ReaffectationFonds
    {
        if (! $auteur->can('reaffectations.decider')) {
            throw new DomainException('Seul le Jewrine national décide une réaffectation de fonds.');
        }

        $origine = $this->fonds($donnees, 'origine');
        $destination = $this->fonds($donnees, 'destination');
        $montant = (int) ($donnees['montant'] ?? 0);
        $motif = trim((string) ($donnees['motif'] ?? ''));

        if ($origine === $destination) {
            throw new DomainException('L\'origine et la destination doivent être différentes.');
        }

        if ($montant < 1) {
            throw new DomainException('Indiquez le montant réaffecté.');
        }

        if ($motif === '') {
            throw new DomainException('Le motif de la réaffectation est obligatoire.');
        }

        /** @var ReaffectationFonds $reaffectation */
        $reaffectation = Numerotation::creer(ReaffectationFonds::class, 'numero', 'RF-'.now()->format('Y').'-', fn (string $numero): ReaffectationFonds => ReaffectationFonds::query()->create([
            'numero' => $numero, 'montant' => $montant, 'motif' => mb_substr($motif, 0, 255), 'decidee_par' => $auteur->id,
            'origine' => $origine['type'], 'origine_collecte_id' => $origine['collecte_id'], 'origine_projet_id' => $origine['projet_id'],
            'destination' => $destination['type'], 'destination_collecte_id' => $destination['collecte_id'], 'destination_projet_id' => $destination['projet_id'],
        ]));

        foreach (User::permission('reaffectations.enregistrer')->get() as $tresorier) {
            if ($tresorier->id !== $auteur->id && ! $tresorier->hasRole('super_administrateur')) {
                $tresorier->notify(new NotificationMembre(
                    'Réaffectation à enregistrer : '.$reaffectation->numero,
                    CollecteService::fcfa($montant).' de « '.$reaffectation->libelleOrigine().' » vers « '.$reaffectation->libelleDestination().' » : '.$motif.'.',
                    route('filament.admin.resources.reaffectations.index'),
                    'Enregistrer',
                ));
            }
        }

        return $reaffectation;
    }

    /**
     * Le fonds désigné : son type et son programme ou son projet.
     *
     * @param  array<string, mixed>  $donnees
     * @return array{type: string, collecte_id: ?int, projet_id: ?int}
     */
    private function fonds(array $donnees, string $cote): array
    {
        $type = (string) ($donnees[$cote] ?? '');

        return match ($type) {
            'programme' => ['type' => $type, 'collecte_id' => Collecte::query()->whereKey((int) ($donnees[$cote.'_collecte_id'] ?? 0))->value('id') ?? throw new DomainException('Choisissez le programme.'), 'projet_id' => null],
            'projet' => ['type' => $type, 'collecte_id' => null, 'projet_id' => Projet::query()->whereKey((int) ($donnees[$cote.'_projet_id'] ?? 0))->value('id') ?? throw new DomainException('Choisissez le projet.')],
            'fonctionnement' => ['type' => $type, 'collecte_id' => null, 'projet_id' => null],
            default => throw new DomainException('Choisissez d\'où vient et où va l\'argent.'),
        };
    }

    public function peutEnregistrer(User $utilisateur, ReaffectationFonds $reaffectation): bool
    {
        return $reaffectation->statut === ReaffectationFonds::DECIDEE && $utilisateur->can('reaffectations.enregistrer') && $reaffectation->decidee_par !== $utilisateur->id;
    }

    /** La Finance enregistre la réaffectation, si le fonds d'origine a de quoi. */
    public function enregistrer(ReaffectationFonds $reaffectation, User $auteur): void
    {
        if (! $this->peutEnregistrer($auteur, $reaffectation)) {
            throw new DomainException('La Finance enregistre une réaffectation décidée (jamais celle qu\'elle a décidée).');
        }

        DB::transaction(function () use ($reaffectation, $auteur): void {
            ReaffectationFonds::query()->whereKey($reaffectation->id)->lockForUpdate()->first();
            $disponible = $this->rapports->soldeFonds($reaffectation->origine, $reaffectation->origine_collecte_id ?? $reaffectation->origine_projet_id);

            if ($disponible < $reaffectation->montant) {
                throw new DomainException('Solde insuffisant : « '.$reaffectation->libelleOrigine().' » n\'a que '.CollecteService::fcfa(max(0, $disponible)).'.');
            }

            $reaffectation->update(['statut' => ReaffectationFonds::ENREGISTREE, 'enregistree_le' => now(), 'enregistree_par' => $auteur->id]);
        });

        $reaffectation->decideur->notify(new NotificationMembre(
            'Réaffectation enregistrée : '.$reaffectation->numero,
            CollecteService::fcfa($reaffectation->montant).' vont désormais à « '.$reaffectation->libelleDestination().' ».',
            route('filament.admin.resources.reaffectations.index'),
            'Voir',
        ));
    }

    public function peutAnnuler(User $utilisateur, ReaffectationFonds $reaffectation): bool
    {
        return $reaffectation->statut === ReaffectationFonds::DECIDEE && $utilisateur->can('reaffectations.decider');
    }

    /** Le Jewrine national annule une réaffectation qui n'est pas encore enregistrée. */
    public function annuler(ReaffectationFonds $reaffectation, User $auteur): void
    {
        if (! $this->peutAnnuler($auteur, $reaffectation)) {
            throw new DomainException('Seule une réaffectation décidée, pas encore enregistrée, s\'annule (par le Jewrine national).');
        }

        $reaffectation->update(['statut' => ReaffectationFonds::ANNULEE]);
    }
}
