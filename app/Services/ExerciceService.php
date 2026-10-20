<?php

namespace App\Services;

use App\Enums\StatutPaiement;
use App\Enums\TypeCaisse;
use App\Models\Caisse;
use App\Models\Exercice;
use App\Models\OrdreDepense;
use App\Models\Paiement;
use App\Models\ReaffectationFonds;
use App\Models\User;
use App\Notifications\NotificationMembre;
use DomainException;

/**
 * La clôture d'un exercice : le Jewrine finance la prépare une fois l'exercice terminé (avec les points encore ouverts et
 * le bilan), le Jewrine national la confirme ou la renvoie. Clos, l'exercice ne se modifie plus et son bilan est figé :
 * une correction faite ensuite compte dans l'exercice où elle a lieu.
 */
final class ExerciceService
{
    public function __construct(private readonly FinanceRapportService $rapports) {}

    /**
     * Ce qui reste ouvert à la fin de l'exercice : à régler de préférence avant la clôture, sans l'empêcher.
     *
     * @return list<string>
     */
    public function alertes(Exercice $exercice): array
    {
        $fin = $exercice->date_fin->copy()->endOfDay();
        $alertes = [];

        $declares = Paiement::query()->where('statut', StatutPaiement::Declare->value)->where('declare_le', '<=', $fin)->count();
        if ($declares > 0) {
            $alertes[] = $declares.' paiement(s) déclaré(s) pendant l\'exercice restent à vérifier.';
        }

        $ordres = OrdreDepense::query()->whereIn('statut', [OrdreDepense::DEMANDE, OrdreDepense::SIGNE])->where('created_at', '<=', $fin)->count();
        if ($ordres > 0) {
            $alertes[] = $ordres.' ordre(s) de dépense de l\'exercice ne sont ni payés ni refusés.';
        }

        $soldes = $this->rapports->soldesAu($exercice->date_fin);

        foreach (Caisse::query()->where('type', TypeCaisse::Representant->value)->get() as $caisse) {
            $solde = $soldes[$caisse->nom] ?? 0;
            if ($solde > 0) {
                $alertes[] = $caisse->nom.' : '.CollecteService::fcfa($solde).' pas encore remis à la Finance.';
            }
        }

        $reaffectations = ReaffectationFonds::query()->where('statut', ReaffectationFonds::DECIDEE)->where('created_at', '<=', $fin)->count();
        if ($reaffectations > 0) {
            $alertes[] = $reaffectations.' réaffectation(s) décidée(s) restent à enregistrer.';
        }

        return $alertes;
    }

    public function peutPreparer(User $utilisateur, Exercice $exercice): bool
    {
        return ! $exercice->estClos() && $exercice->cloture_preparee_le === null && $exercice->date_fin->isPast() && $utilisateur->can('exercices.preparer_cloture');
    }

    /** Le Jewrine finance prépare la clôture : le bilan est calculé avec les points ouverts, le Jewrine national prévenu. */
    public function preparer(Exercice $exercice, User $auteur): void
    {
        if (! $this->peutPreparer($auteur, $exercice)) {
            throw new DomainException('Le Jewrine finance prépare la clôture d\'un exercice terminé, une seule fois.');
        }

        $exercice->update([
            'cloture_preparee_le' => now(), 'cloture_preparee_par' => $auteur->id,
            'bilan' => [...$this->rapports->rapport($exercice->date_debut, $exercice->date_fin), 'alertes' => $this->alertes($exercice)],
        ]);

        foreach (User::permission('exercices.cloturer')->get() as $national) {
            if ($national->id !== $auteur->id && ! $national->hasRole('super_administrateur')) {
                $national->notify(new NotificationMembre(
                    'Clôture à confirmer : exercice '.$exercice->libelle,
                    $auteur->name.' a préparé la clôture et le bilan de l\'exercice '.$exercice->libelle.'.',
                    route('filament.admin.resources.exercices.index'),
                    'Voir le bilan',
                ));
            }
        }
    }

    public function peutConfirmer(User $utilisateur, Exercice $exercice): bool
    {
        return ! $exercice->estClos() && $exercice->cloture_preparee_le !== null && $utilisateur->can('exercices.cloturer') && $exercice->cloture_preparee_par !== $utilisateur->id;
    }

    /** Le Jewrine national confirme : le bilan est recalculé une dernière fois puis figé. */
    public function confirmer(Exercice $exercice, User $auteur): void
    {
        if (! $this->peutConfirmer($auteur, $exercice)) {
            throw new DomainException('Le Jewrine national confirme une clôture préparée (jamais celle qu\'il a préparée).');
        }

        $exercice->update([
            'cloture_le' => now(), 'cloture_par' => $auteur->id,
            'bilan' => [...$this->rapports->rapport($exercice->date_debut, $exercice->date_fin), 'alertes' => $this->alertes($exercice)],
        ]);

        $this->prevenirLePreparateur($exercice, 'Exercice '.$exercice->libelle.' clos', 'La clôture est confirmée ; le bilan est figé.');
    }

    /** Le Jewrine national renvoie la préparation, avec un motif : le Jewrine finance la refera. */
    public function renvoyer(Exercice $exercice, string $motif, User $auteur): void
    {
        if (! $this->peutConfirmer($auteur, $exercice)) {
            throw new DomainException('Le Jewrine national renvoie une clôture préparée.');
        }

        if (trim($motif) === '') {
            throw new DomainException('Indiquez pourquoi la clôture est renvoyée.');
        }

        $preparateur = $exercice->cloture_preparee_par;
        $exercice->update(['cloture_preparee_le' => null, 'cloture_preparee_par' => null, 'bilan' => null]);
        $this->prevenirLePreparateur($exercice, 'Clôture renvoyée : exercice '.$exercice->libelle, trim($motif).'.', $preparateur);
    }

    private function prevenirLePreparateur(Exercice $exercice, string $titre, string $message, ?int $preparateur = null): void
    {
        $utilisateur = User::query()->whereKey($preparateur ?? $exercice->cloture_preparee_par ?? 0)->first();
        $utilisateur?->notify(new NotificationMembre($titre, $message, route('filament.admin.resources.exercices.index'), 'Voir'));
    }
}
