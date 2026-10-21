<?php

namespace App\Filament\Resources\TypesProgramme\Pages;

use App\Filament\Resources\TypesProgramme\TypeProgrammeResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTypesProgramme extends ManageRecords
{
    protected static string $resource = TypeProgrammeResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
