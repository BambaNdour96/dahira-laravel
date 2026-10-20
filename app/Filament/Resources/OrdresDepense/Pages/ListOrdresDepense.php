<?php

namespace App\Filament\Resources\OrdresDepense\Pages;

use App\Filament\Resources\OrdresDepense\OrdreDepenseResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListOrdresDepense extends ListRecords
{
    protected static string $resource = OrdreDepenseResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [OrdreDepenseResource::demander()];
    }
}
