<?php

namespace App\Filament\Resources\ReglesValidation\Pages;

use App\Filament\Resources\ReglesValidation\RegleValidationResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageReglesValidation extends ManageRecords
{
    protected static string $resource = RegleValidationResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nouvelle règle'),
        ];
    }
}
