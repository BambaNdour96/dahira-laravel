<?php

namespace App\Filament\Resources\Instances\Widgets;

use App\Models\Instance;
use App\Services\BudgetComiteService;
use App\Services\CollecteService;
use App\Services\ComiteService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

/** Le tableau de bord d'un comité : compte à rebours, actions, réunions, décisions et collecte liée. */
class TableauDeBordComite extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    /** @return array<Stat> */
    protected function getStats(): array
    {
        if (! $this->record instanceof Instance) {
            return [];
        }

        $avancement = app(ComiteService::class)->avancement($this->record);
        $statistiques = [];

        if ($avancement['jours'] !== null) {
            $statistiques[] = Stat::make('Compte à rebours', $avancement['jours'] >= 0 ? 'J-'.$avancement['jours'] : 'Passé')
                ->description($this->record->dateCible()?->translatedFormat('j F Y') ?? '')
                ->color($avancement['jours'] <= 15 ? 'warning' : 'primary');
        }

        $statistiques[] = Stat::make('Actions faites', $avancement['faites'].' / '.$avancement['actions'])
            ->description($avancement['taux'].' % de l\'ensemble')
            ->color($avancement['taux'] >= 75 ? 'success' : 'gray');
        $statistiques[] = Stat::make('En retard', (string) $avancement['en_retard'])
            ->color($avancement['en_retard'] > 0 ? 'danger' : 'success');
        $statistiques[] = Stat::make('Réunions · décisions', $avancement['reunions'].' · '.$avancement['decisions']);

        if ($this->record->collecte !== null) {
            $statistiques[] = Stat::make('Collecte liée', $this->record->collecte->taux().' %')
                ->description(CollecteService::fcfa($this->record->collecte->totalVerse()).' collectés');
        }

        $budget = app(BudgetComiteService::class)->recapitulatif($this->record);

        if ($budget['budget'] > 0 || $budget['depenses'] > 0) {
            $statistiques[] = Stat::make('Dépenses', CollecteService::fcfa($budget['depenses']))
                ->description('sur un budget de '.CollecteService::fcfa($budget['budget']))
                ->color($budget['depenses'] > $budget['budget'] ? 'danger' : 'gray');
            $statistiques[] = Stat::make('Solde', CollecteService::fcfa($budget['solde']))
                ->description('recettes moins dépenses')
                ->color($budget['solde'] < 0 ? 'danger' : 'success');
        }

        return $statistiques;
    }
}
