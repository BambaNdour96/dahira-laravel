<?php

namespace App\Filament\Resources\Dahiras\Pages;

use App\Filament\Resources\Dahiras\DahiraResource;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDahira extends EditRecord
{
    protected static string $resource = DahiraResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [ViewAction::make()->label('Présences'), DahiraResource::annuler()];
    }
}
