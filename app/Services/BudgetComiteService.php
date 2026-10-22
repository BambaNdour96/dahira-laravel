<?php

namespace App\Services;

use App\Models\BesoinCommission;
use App\Models\CommissionComite;
use App\Models\Instance;
use App\Models\User;
use DomainException;

/**
 * Le budget des commissions d'un comité : les besoins exprimés par les chefs, retenus par l'arbitre (voir SuiviDepensesService) ;
 * le récapitulatif des budgets et des dépenses ; le solde de l'événement. Les dépenses passent par les ordres de dépense
 * (paiement, avance, remboursement) ; les anciennes dépenses déclarées restent en consultation.
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
