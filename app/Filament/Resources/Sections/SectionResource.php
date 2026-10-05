<?php

namespace App\Filament\Resources\Sections;

use App\Filament\Resources\Sections\Pages\ManageSections;
use App\Models\Section;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SectionResource extends Resource
{
    protected static ?string $model = Section::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|UnitEnum|null $navigationGroup = 'Registre';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'section';

    protected static ?string $pluralModelLabel = 'sections';

    protected static ?string $recordTitleAttribute = 'nom';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nom')
                ->label('Nom')
                ->required()
                ->maxLength(100)
                ->unique(ignoreRecord: true),
            TextInput::make('ville')
                ->label('Ville')
                ->required()
                ->maxLength(100),
            TextInput::make('pays')
                ->label('Pays')
                ->required()
                ->default('Sénégal')
                ->maxLength(100),
            Select::make('responsable_id')
                ->label('Responsable')
                ->relationship('responsable', 'name')
                ->searchable()
                ->preload(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nom')->label('Nom')->searchable()->sortable(),
                TextColumn::make('ville')->label('Ville')->searchable(),
                TextColumn::make('pays')->label('Pays')->sortable(),
                TextColumn::make('responsable.name')->label('Responsable')->placeholder('—'),
                TextColumn::make('membres_count')->label('Membres')->counts('membres')->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (Section $record): bool => $record->membres()->exists()),
            ])
            ->defaultSort('nom');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ManageSections::route('/'),
        ];
    }
}
