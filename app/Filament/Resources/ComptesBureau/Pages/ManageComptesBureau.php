<?php

namespace App\Filament\Resources\ComptesBureau\Pages;

use App\Filament\Resources\ComptesBureau\CompteBureauResource;
use App\Filament\Resources\Nominations\NominationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;

class ManageComptesBureau extends ManageRecords
{
    protected static string $resource = CompteBureauResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('nominations')
                ->label('Nommer à un poste')
                ->icon('heroicon-o-identification')
                ->url(fn (): string => NominationResource::getUrl()),
        ];
    }
}
