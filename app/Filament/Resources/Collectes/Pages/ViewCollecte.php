<?php

namespace App\Filament\Resources\Collectes\Pages;

use App\Filament\Resources\Collectes\CollecteResource;
use App\Filament\Resources\Collectes\Widgets\CollecteChiffres;
use App\Filament\Resources\Collectes\Widgets\CollecteEvolution;
use App\Filament\Resources\Collectes\Widgets\CollecteParCellule;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** Le tableau de bord d'une collecte : chiffres, évolution, cellules, puis chaque engagement. */
class ViewCollecte extends ViewRecord
{
    protected static string $resource = CollecteResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            CollecteResource::cloturer(),
            CollecteResource::rouvrir(),
            EditAction::make()->label('Paramètres'),
        ];
    }

    /** @return array<int, class-string> */
    protected function getHeaderWidgets(): array
    {
        return [CollecteChiffres::class, CollecteEvolution::class, CollecteParCellule::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }
}
