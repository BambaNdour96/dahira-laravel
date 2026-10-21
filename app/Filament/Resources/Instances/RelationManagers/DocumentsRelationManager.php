<?php

namespace App\Filament\Resources\Instances\RelationManagers;

use App\Models\OrdreDepense;
use App\Services\CollecteService;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Onglet « Documents » : les devis et les preuves des dépenses de l'événement (les PV sont dans « Réunions et PV »). */
class DocumentsRelationManager extends OngletComite
{
    protected static string $relationship = 'ordresDepense';

    protected static ?string $title = 'Documents';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return OrdresDepenseRelationManager::canViewForRecord($ownerRecord, $pageClass);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where(fn (Builder $pieces) => $pieces->whereNotNull('devis_path')->orWhereNotNull('preuve_path')))
            ->heading('Devis et preuves des dépenses')
            ->columns([
                TextColumn::make('numero')->label('Ordre')->weight('bold'),
                TextColumn::make('objet')->label('Objet')->limit(50),
                TextColumn::make('montant')->label('Montant')->formatStateUsing(fn (int $state): string => CollecteService::fcfa($state)),
            ])
            ->recordActions([
                Action::make('devis')->label('Devis')->icon('heroicon-o-paper-clip')->color('gray')
                    ->visible(fn (OrdreDepense $record): bool => $record->devis_path !== null)
                    ->url(fn (OrdreDepense $record): string => route('ordres-depense.piece', [$record, 'devis']), true),
                Action::make('preuve')->label('Preuve')->icon('heroicon-o-document')->color('gray')
                    ->visible(fn (OrdreDepense $record): bool => $record->preuve_path !== null)
                    ->url(fn (OrdreDepense $record): string => route('ordres-depense.piece', [$record, 'preuve']), true),
            ])
            ->emptyStateHeading('Aucune pièce pour le moment');
    }
}
