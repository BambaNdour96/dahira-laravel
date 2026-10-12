<?php

namespace App\Filament\Resources\Collectes\Pages;

use App\Filament\Resources\Collectes\CollecteResource;
use App\Filament\Resources\Collectes\Widgets\CollecteChiffres;
use App\Filament\Resources\Collectes\Widgets\CollecteEvolution;
use App\Filament\Resources\Collectes\Widgets\CollecteParCellule;
use App\Filament\Resources\Collectes\Widgets\EngagementsDesMembres;
use App\Filament\Resources\Collectes\Widgets\JourJDuProgramme;
use App\Models\Collecte;
use App\Services\JourJService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** Le tableau de bord d'un programme : la collecte (argent), le jour J (présence), puis chaque engagement. */
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $collecte = $this->getRecord();

        return $collecte instanceof Collecte ? JourJService::remplir($collecte, $data) : $data;
    }

    /** @return array<int, class-string> */
    protected function getHeaderWidgets(): array
    {
        return [CollecteChiffres::class, JourJDuProgramme::class, CollecteEvolution::class, CollecteParCellule::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }

    /** @return array<int, class-string> */
    protected function getFooterWidgets(): array
    {
        return [EngagementsDesMembres::class];
    }
}
