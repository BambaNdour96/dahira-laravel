<?php

namespace App\Filament\Resources\Tarifs;

use App\Enums\TypeTarif;
use App\Filament\Resources\Tarifs\Pages\ManageTarifs;
use App\Models\Tarif;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class TarifResource extends Resource
{
    protected static ?string $model = Tarif::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'tarif';

    protected static ?string $pluralModelLabel = 'tarifs';

    protected static ?string $recordTitleAttribute = 'libelle';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->label('Type')
                ->options(TypeTarif::class)
                ->required()
                ->live(),
            TextInput::make('libelle')
                ->label('Libellé')
                ->required()
                ->maxLength(100),
            TextInput::make('montant')
                ->label('Montant')
                ->required()
                ->integer()
                ->minValue(0)
                ->suffix('FCFA'),
            Select::make('exercice_id')
                ->label('Exercice')
                ->relationship('exercice', 'libelle')
                ->preload()
                ->required(fn (Get $get): bool => self::estCotisation($get('type')))
                ->helperText('Obligatoire pour une cotisation.'),
            Toggle::make('actif')
                ->label('Actif')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')->label('Type')->badge()->sortable(),
                TextColumn::make('libelle')->label('Libellé')->searchable(),
                TextColumn::make('montant')
                    ->label('Montant')
                    ->numeric(decimalPlaces: 0, thousandsSeparator: ' ')
                    ->suffix(' FCFA')
                    ->sortable(),
                TextColumn::make('exercice.libelle')->label('Exercice')->placeholder('—'),
                IconColumn::make('actif')->label('Actif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Type')->options(TypeTarif::class),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ManageTarifs::route('/'),
        ];
    }

    private static function estCotisation(mixed $type): bool
    {
        return $type === TypeTarif::Cotisation || $type === TypeTarif::Cotisation->value;
    }
}
