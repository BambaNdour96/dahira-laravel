<?php

namespace App\Filament\Resources\Membres\Pages;

use App\Filament\Exports\MembreExporter;
use App\Filament\Resources\ImportsMembres\ImportMembresResource;
use App\Filament\Resources\Membres\MembreResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
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
                    && $utilisateur->can('membres.exporter')),
            Action::make('reprise')
                ->label('Reprise des données')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->url(fn (): string => ImportMembresResource::getUrl('index'))
                ->visible(fn (): bool => ImportMembresResource::canViewAny()),
            CreateAction::make(),
        ];
    }
}
