<?php

namespace App\Filament\Resources\Membres\Pages;

use App\Filament\Resources\Membres\MembreResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMembres extends ListRecords
{
    protected static string $resource = MembreResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
