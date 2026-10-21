<?php

namespace App\Filament\Resources\Instances\RelationManagers;

use App\Filament\Resources\Reunions\ReunionResource;
use App\Models\Reunion;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Onglet « Réunions et PV » : les réunions du comité, leur PV publié, et l'accès à chacune. */
class ReunionsRelationManager extends OngletComite
{
    protected static string $relationship = 'reunions';

    protected static ?string $title = 'Réunions et PV';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titre')->label('Réunion')->weight('bold'),
                TextColumn::make('debut')->label('Date')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('lieu')->label('Lieu'),
                TextColumn::make('pv')->label('PV')->badge()
                    ->state(fn (Reunion $record): string => $record->estPubliee() ? 'Publié' : ($record->estAnnule() ? 'Annulée' : 'À venir ou en rédaction'))
                    ->color(fn (Reunion $record): string => $record->estPubliee() ? 'success' : 'gray'),
            ])
            ->recordActions([
                Action::make('ouvrir')->label('Ouvrir')->icon('heroicon-o-eye')->url(fn (Reunion $record): string => ReunionResource::getUrl('view', ['record' => $record])),
                Action::make('pdf')->label('PV (PDF)')->icon('heroicon-o-document-arrow-down')->color('gray')
                    ->visible(fn (Reunion $record): bool => $record->estPubliee())
                    ->url(fn (Reunion $record): string => route('reunions.pv', $record), true),
            ])
            ->defaultSort('debut', 'desc');
    }
}
