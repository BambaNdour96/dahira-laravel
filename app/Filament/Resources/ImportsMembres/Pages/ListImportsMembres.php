<?php

namespace App\Filament\Resources\ImportsMembres\Pages;

use App\Filament\Resources\ImportsMembres\ImportMembresResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListImportsMembres extends ListRecords
{
    protected static string $resource = ImportMembresResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [ImportMembresResource::analyser(), ImportMembresResource::modele()];
    }
}
