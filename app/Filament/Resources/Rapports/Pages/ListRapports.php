<?php

namespace App\Filament\Resources\Rapports\Pages;

use App\Filament\Resources\Rapports\RapportResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRapports extends ListRecords
{
    protected static string $resource = RapportResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Rédiger un rapport')];
    }
}
