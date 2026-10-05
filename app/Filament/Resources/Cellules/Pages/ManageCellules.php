<?php

namespace App\Filament\Resources\Cellules\Pages;

use App\Filament\Resources\Cellules\CelluleResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCellules extends ManageRecords
{
    protected static string $resource = CelluleResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
