<?php

namespace App\Filament\Resources\Membres\Pages;

use App\Filament\Imports\MembreImporter;
use App\Filament\Resources\Membres\MembreResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListMembres extends ListRecords
{
    protected static string $resource = MembreResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->label('Importer (CSV)')
                ->importer(MembreImporter::class)
                ->visible(fn (): bool => ($utilisateur = Auth::user()) instanceof User
                    && $utilisateur->hasRole('administrateur')),
            CreateAction::make(),
        ];
    }
}
