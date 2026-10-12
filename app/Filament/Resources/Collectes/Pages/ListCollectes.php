<?php

namespace App\Filament\Resources\Collectes\Pages;

use App\Filament\Resources\Collectes\CollecteResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCollectes extends ListRecords
{
    protected static string $resource = CollecteResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('fiche')
                ->label('Fiche de paramétrage (PDF)')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn (): string => route('collectes.fiche')),
            CreateAction::make()->label('Nouveau programme'),
        ];
    }
}
