<?php

namespace App\Filament\Resources\Projets\Widgets;

use App\Models\Projet;
use App\Services\CollecteService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

/** Les chiffres clés et le feu tricolore d'un projet. */
class ProjetChiffres extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    /** @return array<Stat> */
    protected function getStats(): array
    {
        if (! $this->record instanceof Projet) {
            return [];
        }

        $projet = $this->record;
        [$sante, $raison] = $projet->diagnostic();
        $etapes = $projet->jalons()->count();
        $realisees = $projet->jalons()->whereNotNull('realise_le')->count();
        $depenses = $projet->depenses();

        $stats = [
            Stat::make('Santé du projet', $sante->getLabel())
                ->description($raison)
                ->color($sante->getColor()),
            Stat::make('Avancement', $projet->avancement().' %')
                ->description($realisees.' étape(s) réalisée(s) sur '.$etapes)
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('primary'),
            Stat::make('Temps écoulé', $projet->tempsEcoule().' %')
                ->description('Du '.$projet->date_debut->format('d/m/Y').' au '.$projet->date_fin->format('d/m/Y'))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($projet->tempsEcoule() > $projet->avancement() + 25 ? 'warning' : 'gray'),
            Stat::make('Dépenses', CollecteService::fcfa($depenses))
                ->description($projet->budget !== null ? 'Budget : '.CollecteService::fcfa($projet->budget) : 'Pas de budget fixé')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($projet->budget !== null && $depenses > $projet->budget ? 'danger' : 'gray'),
        ];

        if ($projet->collecte !== null) {
            $stats[] = Stat::make('Financement collecté', CollecteService::fcfa($projet->collecte->totalVerse()))
                ->description($projet->collecte->titre.' · '.$projet->collecte->taux().' %')
                ->descriptionIcon('heroicon-m-gift')
                ->color('success');
        }

        return $stats;
    }
}
