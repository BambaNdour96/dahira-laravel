<?php

namespace App\Filament\Widgets;

use App\Enums\MoyenPaiement;
use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\User;
use App\Services\CotisationService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class RapportFinancier extends StatsOverviewWidget
{
    protected ?string  = null;

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User && $utilisateur->hasAnyRole(['administrateur', 'tresorier']);
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $confirmes = Paiement::query()->where('statut', StatutPaiement::Confirme->value);

        $ceMois = (int) (clone $confirmes)->where('traite_le', '>=', now()->startOfMonth())->sum('montant');
        $annee = (clone $confirmes)->where('traite_le', '>=', now()->startOfYear());
        $totalAnnee = (int) (clone $annee)->sum('montant');
        $wave = (int) (clone $annee)->where('moyen', MoyenPaiement::Wave->value)->sum('montant');

        $valides = Membre::query()->where('statut', StatutMembre::Valide->value)->get();
        $retards = app(CotisationService::class)->retards($valides);
        $total = count($retards);
        $aJour = count(array_filter($retards, fn (int $nombre): bool => $nombre === 0));
        $taux = $total > 0 ? (int) round($aJour / $total * 100) : 100;

        return [
            Stat::make('Encaissé ce mois', self::fcfa($ceMois))
                ->color('success'),
            Stat::make('Encaissé cette année', self::fcfa($totalAnnee))
                ->description('Wave : '.self::fcfa($wave).' · Espèces : '.self::fcfa($totalAnnee - $wave))
                ->color('success'),
            Stat::make('Membres à jour', $taux.' %')
                ->description($aJour.' sur '.$total.' membres validés')
                ->color($taux >= 80 ? 'success' : 'warning'),
        ];
    }

    private static function fcfa(int $montant): string
    {
        return number_format($montant, 0, ',', ' ').' FCFA';
    }
}
