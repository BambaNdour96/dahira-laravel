<?php

namespace App\Filament\Resources\Paiements\Pages;

use App\Filament\Exports\PaiementExporter;
use App\Filament\Resources\Paiements\PaiementResource;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Resources\Pages\ManageRecords;

class ManagePaiements extends ManageRecords
{
    protected static string $resource = PaiementResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->label('Exporter (Excel)')
                ->exporter(PaiementExporter::class)
                ->formats([ExportFormat::Xlsx, ExportFormat::Csv]),
        ];
    }
}
