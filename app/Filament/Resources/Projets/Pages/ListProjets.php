<?php

namespace App\Filament\Resources\Projets\Pages;

use App\Filament\Resources\Projets\ProjetResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProjets extends ListRecords
{
    protected static string $resource = ProjetResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nouveau projet')];
    }
}
