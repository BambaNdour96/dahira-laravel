<?php

namespace App\Filament\Resources\LibellesDepense\Pages;

use App\Filament\Resources\LibellesDepense\LibelleDepenseResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLibellesDepense extends ManageRecords
{
    protected static string $resource = LibelleDepenseResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
