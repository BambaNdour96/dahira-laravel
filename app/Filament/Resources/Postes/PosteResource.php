<?php

namespace App\Filament\Resources\Postes;

use App\Filament\Resources\Postes\Pages\ManagePostes;
use App\Models\Poste;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PosteResource extends Resource
{
    protected static ?string $model = Poste::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static string|UnitEnum|null $navigationGroup = 'Registre';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'poste';

    protected static ?string $pluralModelLabel = 'postes';

    protected static ?string $recordTitleAttribute = 'nom';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nom')->label('Nom du poste')->required()->maxLength(100)->unique(ignoreRecord: true),
            TextInput::make('ordre')->label('Ordre dans l\'organigramme')->integer()->minValue(0)->default(0),
            Toggle::make('actif')->label('Actif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ordre')->label('Ordre')->sortable(),
                TextColumn::make('nom')->label('Poste')->searchable(),
                TextColumn::make('membres_count')->label('Membres')->counts('membres'),
                IconColumn::make('actif')->label('Actif')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('ordre');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ManagePostes::route('/'),
        ];
    }
}
