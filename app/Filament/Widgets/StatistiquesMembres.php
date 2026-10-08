<?php

namespace App\Filament\Widgets;

use App\Enums\StatutPaiement;
use App\Filament\Resources\Membres\MembreResource;
use App\Models\Membre;
use App\Models\Paiement;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Compteurs du tableau de bord, limités aux cellules visibles par l'utilisateur.
 */
class StatistiquesMembres extends StatsOverviewWidget
{
    protected ?string  = null;

    protected static ?int $sort = 1;

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $parStatut = MembreResource::getEloquentQuery()
            ->toBase()
            ->selectRaw('statut, count(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut');

        $total = (int) $parStatut->sum();

        $incomplets = MembreResource::getEloquentQuery()
            ->where('statut', 'valide')
            ->get()
            ->filter(fn ($membre): bool => $membre instanceof Membre && $membre->completude() < 100)
            ->count();

        $paiementsAVerifier = Paiement::query()
            ->where('statut', StatutPaiement::Declare->value)
            ->whereIn('membre_id', MembreResource::getEloquentQuery()->select('membres.id'))
            ->count();

        return [
            Stat::make('Membres validés', (int) $parStatut->get('valide', 0))
                ->description('sur '.$total.' inscrits')
                ->color('success'),
            Stat::make('Inscriptions à valider', (int) $parStatut->get('en_attente', 0))
                ->description('À traiter par les Jewrine')
                ->color('warning'),
            Stat::make('Acceptés, paiement attendu', (int) $parStatut->get('accepte', 0))
                ->description('Frais de carte non encore réglés')
                ->color('primary'),
            Stat::make('Paiements à vérifier', $paiementsAVerifier)
                ->description('Déclarations Wave à confirmer')
                ->color('warning'),
            Stat::make('Fiches à corriger', (int) $parStatut->get('a_corriger', 0))
                ->description('En attente du membre')
                ->color('info'),
            Stat::make('Profils incomplets', $incomplets)
                ->description('Membres validés à relancer')
                ->color('gray'),
        ];
    }
}
