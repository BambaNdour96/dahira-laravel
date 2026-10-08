<?php

namespace App\Filament\Resources\Evenements\Pages;

use App\Filament\Resources\Evenements\EvenementResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEvenements extends ManageRecords
{
    protected static string $resource = EvenementResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nouvel événement'),
        ];
    }
}
