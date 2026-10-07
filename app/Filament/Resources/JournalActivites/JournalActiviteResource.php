<?php

namespace App\Filament\Resources\JournalActivites;

use App\Filament\Resources\JournalActivites\Pages\ListJournalActivites;
use App\Models\JournalActivite;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class JournalActiviteResource extends Resource
{
    protected static ?string $model = JournalActivite::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 9;

    protected static ?string $modelLabel = 'entrée du journal';

    protected static ?string $pluralModelLabel = 'journal d\'activité';

    protected static ?string $navigationLabel = 'Journal d\'activité';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Auteur')
                    ->placeholder('Système'),
                TextColumn::make('sujet_type')
                    ->label('Objet')
                    ->formatStateUsing(fn (string $state): string => class_basename($state)),
                TextColumn::make('sujet_id')
                    ->label('N°'),
                TextColumn::make('evenement')
                    ->label('Action')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cree' => 'Création',
                        'modifie' => 'Modification',
                        'supprime' => 'Suppression',
                        'en_tant_que' => 'Connexion en tant que',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'cree' => 'success',
                        'supprime' => 'danger',
                        default => 'info',
                    }),
                TextColumn::make('changements')
                    ->label('Changements')
                    ->state(fn (JournalActivite $record): string => $record->resume())
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('evenement')
                    ->label('Action')
                    ->options([
                        'cree' => 'Création',
                        'modifie' => 'Modification',
                        'supprime' => 'Suppression',
                    ]),
                SelectFilter::make('sujet_type')
                    ->label('Objet')
                    ->options([
                        'App\\Models\\Membre' => 'Membre',
                        'App\\Models\\Cellule' => 'Cellule',
                        'App\\Models\\Exercice' => 'Exercice',
                        'App\\Models\\Tarif' => 'Tarif',
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListJournalActivites::route('/'),
        ];
    }
}
