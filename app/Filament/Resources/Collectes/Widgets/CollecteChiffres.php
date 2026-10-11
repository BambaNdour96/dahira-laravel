<?php

namespace App\Filament\Resources\Collectes\Widgets;

use App\Models\Collecte;
use App\Services\CollecteService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

/** Les chiffres clés d'une collecte, en tête de son tableau de bord. */
class CollecteChiffres extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    /** @return array<Stat> */
    protected function getStats(): array
    {
        if (! $this->record instanceof Collecte) {
            return [];
        }

        $collecte = $this->record;
        $chiffres = app(CollecteService::class)->statistiques($collecte);
        $fcfa = fn (int $montant): string => CollecteService::fcfa($montant);

        return [
            Stat::make('Collecté', $fcfa($chiffres['total_verse']))
                ->description($chiffres['taux'].' % de '.$fcfa($chiffres['cible']).($collecte->objectif !== null ? ' (objectif)' : ' (engagements)'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($chiffres['taux'] >= 100 ? 'success' : ($chiffres['taux'] >= 50 ? 'warning' : 'primary')),
            Stat::make('Engagé par les membres', $fcfa($chiffres['total_engage']))
                ->description($chiffres['engages'].' membre(s) engagé(s) sur '.$chiffres['concernes'].' concerné(s)')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),
            Stat::make('À jour', $chiffres['a_jour'].' / '.$chiffres['engages'])
                ->description('Engagements entièrement réglés')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
            Stat::make('En retard', (string) $chiffres['en_retard'])
                ->description($collecte->date_limite !== null ? 'Date limite : '.$collecte->date_limite->format('d/m/Y') : 'Pas de date limite')
                ->descriptionIcon('heroicon-m-clock')
                ->color($chiffres['en_retard'] > 0 ? 'danger' : 'gray'),
        ];
    }
}
