<?php

namespace App\Filament\Resources\CampagnesRenouvellement\Pages;

use App\Filament\Resources\CampagnesRenouvellement\CampagneRenouvellementResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListCampagnesRenouvellement extends ListRecords
{
    protected static string $resource = CampagneRenouvellementResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [CampagneRenouvellementResource::ouvrir()];
    }
}
