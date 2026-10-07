<?php

namespace App\Filament\Resources\Connexions;

use App\Filament\Resources\Connexions\Pages\ListConnexions;
use App\Models\Connexion;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ConnexionResource extends Resource
{
    protected static ?string $model = Connexion::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-finger-print';

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'connexion';

    protected static ?string $pluralModelLabel = 'historique des connexions';

    protected static ?string $navigationLabel = 'Historique des connexions';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')->label('Date')->dateTime('d/m/Y H:i:s')->sortable(),
                IconColumn::make('reussie')->label('Réussie')->boolean(),
                TextColumn::make('user.name')->label('Compte')->placeholder('Inconnu')->searchable(),
                TextColumn::make('telephone')->label('Téléphone saisi')->searchable()->placeholder('—'),
                TextColumn::make('ip')->label('Adresse IP')->placeholder('—'),
                TextColumn::make('navigateur')->label('Appareil')->limit(45)->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('reussie')
                    ->label('Résultat')
                    ->options(['1' => 'Réussies', '0' => 'Échouées']),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListConnexions::route('/'),
        ];
    }
}
