<?php

namespace App\Filament\Resources\Paiements\Pages;

use App\Enums\StatutPaiement;
use App\Filament\Exports\PaiementExporter;
use App\Filament\Resources\Paiements\PaiementResource;
use App\Models\Paiement;
use App\Models\RegleValidation;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

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
                ->badge(fn (): int => self::aVerifier(Paiement::query())->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => self::aVerifier($query)),
            'bureau' => Tab::make('Paiements du bureau')
                ->badge(fn (): int => self::duBureau(Paiement::query())->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => self::duBureau($query)),
            'confirmes' => Tab::make('Confirmés')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('statut', StatutPaiement::Confirme->value)),
            'refuses' => Tab::make('Refusés')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('statut', StatutPaiement::Refuse->value)),
            'tous' => Tab::make('Tous'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'a_verifier';
    }

    /**
     * Déclarations des membres ordinaires.
     *
     * @template TQuery of Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    private static function aVerifier(Builder $query): Builder
    {
        return RegleValidation::paiementsSoumis($query->where('statut', StatutPaiement::Declare->value), soumis: false);
    }

    /**
     * Déclarations des postes clés du bureau, soumises à la validation croisée.
     *
     * @template TQuery of Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    private static function duBureau(Builder $query): Builder
    {
        return RegleValidation::paiementsSoumis($query->where('statut', StatutPaiement::Declare->value));
    }
}
