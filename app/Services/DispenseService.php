<?php

namespace App\Services;

use App\Enums\StatutMembre;
use App\Models\DemandeDispense;
use App\Models\Membre;
use App\Models\Nomination;
use App\Models\Poste;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Observers\AvisMembreObserver;
use App\Policies\MembrePolicy;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * La dispense de cotisation : le Jewrine de cellule la demande pour un membre de sa cellule (le super administrateur
 * aussi, en secours) ; le Jewrine national la valide ou la refuse. Personne ne valide sa propre demande, ni la dispense
 * d'un membre qui le touche (lui-même, un proche dont il gère la fiche).
 */
final class DispenseService
{
    public function __construct(
        private readonly CasquettesService $casquettes,
        private readonly CotisationService $cotisations,
    ) {}

    public function peutDemander(User $utilisateur, Membre $membre): bool
    {
        return $this->casquettes->peut($utilisateur, 'cotisations.dispense_demander', $membre->cellule_id)
            && $membre->user_id !== $utilisateur->id
            && ! MembrePolicy::gereLaFiche($utilisateur, $membre);
    }

    public function demander(Membre $membre, int $nombreMois, string $motif, User $auteur): DemandeDispense
    {
        if (! $this->peutDemander($auteur, $membre)) {
            throw new DomainException('La dispense est demandée par le Jewrine de la cellule du membre.');
        }

        if ($membre->statut !== StatutMembre::Valide) {
            throw new DomainException('Seuls les membres validés peuvent être dispensés.');
        }

        if ($nombreMois < 1 || $nombreMois > 12) {
            throw new DomainException('On peut demander de 1 à 12 mois de dispense.');
        }

        if (trim($motif) === '') {
            throw new DomainException('Le motif de la dispense est obligatoire.');
        }

        if (DemandeDispense::query()->where('membre_id', $membre->id)->where('statut', DemandeDispense::EN_ATTENTE)->exists()) {
            throw new DomainException('Une demande de dispense attend déjà la décision du Jewrine national pour ce membre.');
        }

        $demande = DemandeDispense::query()->create([
            'membre_id' => $membre->id, 'nombre_mois' => $nombreMois, 'motif' => mb_substr(trim($motif), 0, 255), 'demandee_par' => $auteur->id,
        ]);

        $nationaux = User::query()->whereIn('id', Nomination::query()->whereHas('poste', fn ($query) => $query->where('code', Poste::JEWRINE_NATIONAL))->select('user_id'))->get();

        foreach ($nationaux as $national) {
            $national->notify(new NotificationMembre(
                'Dispense à décider : '.$membre->nom_complet,
                $auteur->name.' demande '.$nombreMois.' mois de dispense de cotisation pour '.$membre->nom_complet.' ('.$membre->cellule->nom.') : « '.$demande->motif.' ».',
                route('filament.admin.resources.dispenses.index'),
                'Décider',
            ));
        }

        return $demande;
    }

    public function peutDecider(User $utilisateur, DemandeDispense $demande): bool
    {
        return $utilisateur->can('cotisations.dispenser')
            && $demande->demandee_par !== $utilisateur->id
            && $demande->membre->user_id !== $utilisateur->id
            && ! MembrePolicy::gereLaFiche($utilisateur, $demande->membre);
    }

    /** Validée : les mois sont dispensés (les mois de retard d'abord) ; le demandeur et le membre sont prévenus. */
    public function valider(DemandeDispense $demande, User $auteur): void
    {
        $this->verifierDecision($demande, $auteur);

        DB::transaction(function () use ($demande, $auteur): void {
            $this->cotisations->exonerer($demande->membre, $demande->nombre_mois, $demande->motif);
            $demande->update(['statut' => DemandeDispense::VALIDEE, 'decide_le' => now(), 'decide_par' => $auteur->id]);
        });

        $this->prevenir($demande, 'Dispense validée', $demande->nombre_mois.' mois de dispense de cotisation pour '.$demande->membre->nom_complet.'.');
        AvisMembreObserver::avertir($demande->membre, 'Dispense de cotisation', 'Vous êtes dispensé de '.$demande->nombre_mois.' mois de cotisation.', route('cotisations'), 'Mes cotisations');
    }

    public function refuser(DemandeDispense $demande, string $motif, User $auteur): void
    {
        $this->verifierDecision($demande, $auteur);

        if (trim($motif) === '') {
            throw new DomainException('Indiquez le motif du refus.');
        }

        $demande->update(['statut' => DemandeDispense::REFUSEE, 'decide_le' => now(), 'decide_par' => $auteur->id, 'motif_refus' => mb_substr(trim($motif), 0, 255)]);
        $this->prevenir($demande, 'Dispense refusée', 'La dispense de '.$demande->membre->nom_complet.' n\'est pas accordée : '.trim($motif).'.');
    }

    private function verifierDecision(DemandeDispense $demande, User $auteur): void
    {
        if ($demande->statut !== DemandeDispense::EN_ATTENTE) {
            throw new DomainException('Cette demande a déjà été décidée.');
        }

        if (! $this->peutDecider($auteur, $demande)) {
            throw new DomainException('Le Jewrine national décide de la dispense (jamais sur sa propre demande).');
        }
    }

    private function prevenir(DemandeDispense $demande, string $titre, string $message): void
    {
        $demande->demandeur->notify(new NotificationMembre($titre, $message, route('filament.admin.resources.dispenses.index'), 'Voir'));
    }
}
