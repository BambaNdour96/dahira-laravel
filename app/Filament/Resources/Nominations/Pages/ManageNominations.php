<?php

namespace App\Filament\Resources\Nominations\Pages;

use App\Filament\Resources\Nominations\NominationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;

class ManageNominations extends ManageRecords
{
    protected static string $resource = NominationResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [NominationResource::nommer()];
    }
}
