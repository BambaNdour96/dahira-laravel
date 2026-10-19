<?php

namespace App\Filament\Resources\LotsCartes\Pages;

use App\Filament\Resources\LotsCartes\LotCartesResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListLotsCartes extends ListRecords
{
    protected static string $resource = LotCartesResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [LotCartesResource::preparer()];
    }
}
