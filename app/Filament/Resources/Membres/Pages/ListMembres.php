<?php

namespace App\Filament\Resources\Membres\Pages;

use App\Filament\Exports\MembreExporter;
use App\Filament\Imports\MembreImporter;
use App\Filament\Resources\Membres\MembreResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
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
            ExportAction::make()
                ->label('Exporter (Excel)')
                ->exporter(MembreExporter::class)
                ->formats([ExportFormat::Xlsx, ExportFormat::Csv])
                ->visible(fn (): bool => ($utilisateur = Auth::user()) instanceof User
                    && $utilisateur->hasAnyRole(['administrateur', 'tresorier'])),
            ImportAction::make()
                ->label('Importer (CSV)')
                ->importer(MembreImporter::class)
                ->visible(fn (): bool => ($utilisateur = Auth::user()) instanceof User
                    && $utilisateur->hasRole('administrateur')),
            CreateAction::make(),
        ];
    }
}
