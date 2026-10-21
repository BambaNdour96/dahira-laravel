<?php

namespace App\Services;

use App\Models\BesoinCommission;
use App\Models\CommissionComite;
use App\Models\DepenseCommission;
use App\Models\Instance;
use App\Models\User;
use App\Notifications\NotificationMembre;
use DomainException;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;

/**
 * Le budget des commissions d'un comité : les besoins exprimés par les chefs, retenus par l'arbitre (voir SuiviDepensesService) ;
 * les dépenses déclarées par les chefs, validées par le Jewrine finance ; le solde du Magal.
 */
final class BudgetComiteService
{
    /** Le chef de la commission, ou les organisateurs du comité. */
    public static function peutSaisir(User $utilisateur, CommissionComite $commission): bool
    {
        return ($commission->chef !== null && $commission->chef->user_id === $utilisateur->id)
            || ComiteService::peutOrganiser($utilisateur, $commission->instance);
    }

    /** Retenir ou écarter un besoin fait partie de l'arbitrage (comité de suivi, sinon Jewrine national). */
    public static function peutRetenir(User $utilisateur, CommissionComite $commission): bool
    {
        return app(SuiviDepensesService::class)->peutArbitrer($utilisateur, $commission->instance);
    }

    public static function peutValider(User $utilisateur, DepenseCommission $depense): bool
    {
        return $utilisateur->can('comites.depenses_valider') && $depense->declaree_par !== $utilisateur->id;
    }

    /** @param  array{designation: string, quantite: int, prix_unitaire: int}  $donnees */
    public function proposerBesoin(CommissionComite $commission, array $donnees, User $auteur): BesoinCommission
    {
        if (! self::peutSaisir($auteur, $commission)) {
            throw new DomainException('Seul le chef de la commission exprime ses besoins.');
        }

        return BesoinCommission::query()->create([...$donnees, 'commission_id' => $commission->id, 'propose_par' => $auteur->id]);
    }

    public function retenir(BesoinCommission $besoin, User $auteur): void
    {
        $this->traiterBesoin($besoin, 'retenu', null, $auteur);
    }

    public function ecarter(BesoinCommission $besoin, string $motif, User $auteur): void
    {
        $this->traiterBesoin($besoin, 'ecarte', $motif, $auteur);
    }

    private function traiterBesoin(BesoinCommission $besoin, string $statut, ?string $motif, User $auteur): void
    {
        if (! self::peutRetenir($auteur, $besoin->commission)) {
            throw new DomainException('Les besoins sont retenus ou écartés par celui qui arbitre le budget (le Jewrine du suivi des dépenses, sinon le Jewrine national).');
        }

        $besoin->update(['statut' => $statut, 'motif' => $motif, 'traite_par' => $auteur->id]);
    }

    /** @param  array{objet: string, montant: int, date_depense: string, fournisseur?: string|null}  $donnees */
    public function declarerDepense(CommissionComite $commission, array $donnees, ?UploadedFile $justificatif, User $auteur): DepenseCommission
    {
        if (! self::peutSaisir($auteur, $commission)) {
            throw new DomainException('Seul le chef de la commission déclare ses dépenses.');
        }

        $depense = DepenseCommission::query()->create([
            ...$donnees,
            'commission_id' => $commission->id,
            'justificatif_path' => $justificatif?->store('justificatifs', 'local'),
            'declaree_par' => $auteur->id,
        ]);

        $tresoriers = User::role(['jewrine_finance', 'super_administrateur'])->where('id', '!=', $auteur->id)->get();

        if ($tresoriers->isNotEmpty()) {
            Notification::make()
                ->title('Dépense à valider : '.$commission->nom)
                ->body($depense->objet.' — '.CollecteService::fcfa($depense->montant).' ('.$commission->instance->nom.')')
                ->info()
                ->sendToDatabase($tresoriers);
        }

        return $depense;
    }

    public function valider(DepenseCommission $depense, User $auteur): void
    {
        if (! self::peutValider($auteur, $depense) || $depense->statut !== 'declaree') {
            throw new DomainException('Le Jewrine finance valide les dépenses déclarées (jamais les siennes).');
        }

        $depense->update(['statut' => 'validee', 'traitee_par' => $auteur->id, 'traitee_le' => now()]);
        $commission = $depense->commission;
        $budget = $commission->budget();

        if ($budget > 0 && $commission->depense() > $budget) {
            $alertes = User::role(['jewrine_finance', 'super_administrateur'])->get();
            $president = $commission->instance->president;

            if ($president !== null && $president->user_id !== null && ($compte = User::query()->find($president->user_id)) !== null) {
                $alertes->push($compte);
            }

            Notification::make()
                ->title('Budget dépassé : commission '.$commission->nom)
                ->body(CollecteService::fcfa($commission->depense()).' dépensés pour un budget de '.CollecteService::fcfa($budget).'.')
                ->danger()
                ->sendToDatabase($alertes->unique('id'));
        }
    }

    public function refuser(DepenseCommission $depense, string $motif, User $auteur): void
    {
        if (! self::peutValider($auteur, $depense) || $depense->statut !== 'declaree') {
            throw new DomainException('Le Jewrine finance valide les dépenses déclarées (jamais les siennes).');
        }

        $depense->update(['statut' => 'refusee', 'motif' => $motif, 'traitee_par' => $auteur->id, 'traitee_le' => now()]);

        $depense->declarant?->notify(new NotificationMembre(
            'Dépense refusée',
            $depense->objet.' ('.CollecteService::fcfa($depense->montant).') : '.$motif,
            route('comites.index'),
            'Voir mes comités',
        ));
    }

    /**
     * Le budget et les dépenses de chaque commission, les recettes et le solde.
     *
     * @return array{commissions: list<array{nom: string, budget: int, depense: int, reste: int, taux: int}>, budget: int, depenses: int, recettes: int, solde: int}
     */
    public function recapitulatif(Instance $instance): array
    {
        $commissions = [];

        foreach ($instance->commissions()->get() as $commission) {
            $budget = $commission->budget();
            $depense = $commission->depense();
            $commissions[] = [
                'nom' => $commission->nom,
                'budget' => $budget,
                'depense' => $depense,
                'reste' => $budget - $depense,
                'taux' => $budget > 0 ? (int) round($depense / $budget * 100) : 0,
            ];
        }

        $depenses = array_sum(array_column($commissions, 'depense'));
        $recettes = $instance->collecte?->totalVerse() ?? 0;

        return [
            'commissions' => $commissions,
            'budget' => array_sum(array_column($commissions, 'budget')),
            'depenses' => $depenses,
            'recettes' => $recettes,
            'solde' => $recettes - $depenses,
        ];
    }
}
