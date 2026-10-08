<?php

namespace App\Filament\Resources\Paiements\Pages;

use App\Enums\StatutPaiement;
use App\Filament\Exports\PaiementExporter;
use App\Filament\Resources\Paiements\PaiementResource;
use App\Models\Paiement;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;

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

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        return [
            'a_verifier' => Tab::make('À vérifier')
                ->badge(fn (): int => Paiement::query()->where('statut', StatutPaiement::Declare->value)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('statut', StatutPaiement::Declare->value)),
            'confirmes' => Tab::make('Confirmés')
                ->modifyQueryUsing(fn ($query) => $query->where('statut', StatutPaiement::Confirme->value)),
            'refuses' => Tab::make('Refusés')
                ->modifyQueryUsing(fn ($query) => $query->where('statut', StatutPaiement::Refuse->value)),
            'tous' => Tab::make('Tous'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'a_verifier';
    }
}
