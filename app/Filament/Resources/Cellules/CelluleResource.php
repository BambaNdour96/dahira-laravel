<?php

namespace App\Filament\Resources\Cellules;

use App\Filament\Actions\ConservationActions;
use App\Filament\Resources\Cellules\Pages\ManageCellules;
use App\Models\Cellule;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class CelluleResource extends Resource
{
    protected static ?string $model = Cellule::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|UnitEnum|null $navigationGroup = 'Registre';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'cellule';

    protected static ?string $pluralModelLabel = 'cellules';

    protected static ?string $recordTitleAttribute = 'nom';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nom')->label('Nom')->required()->maxLength(100)->unique(ignoreRecord: true),
            TextInput::make('ville')->label('Ville')->required()->maxLength(100),
            TextInput::make('pays')->label('Pays')->required()->default('Sénégal')->maxLength(100),
            Select::make('responsable_id')
                ->label('Jewrine')
                ->relationship('responsable', 'name')
                ->searchable()
                ->preload(),
            TextInput::make('whatsapp_lien')
                ->label('Lien du groupe WhatsApp')
                ->url()
                ->maxLength(255)
                ->placeholder('https://chat.whatsapp.com/...')
                ->rules(['nullable', 'starts_with:https://chat.whatsapp.com/'])
                ->validationMessages(['starts_with' => 'Le lien doit commencer par https://chat.whatsapp.com/'])
                ->helperText('Visible uniquement par les membres validés de la cellule.')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nom')->label('Nom')->searchable()->sortable(),
                TextColumn::make('ville')->label('Ville')->searchable(),
                TextColumn::make('responsable.name')->label('Jewrine')->placeholder('—'),
                TextColumn::make('membres_count')->label('Membres')->counts('membres')->sortable(),
                TextColumn::make('archive_le')->label('Archivée le')->date('d/m/Y')->placeholder('—')->toggleable(),
                IconColumn::make('whatsapp_lien')
                    ->label('WhatsApp')
                    ->boolean()
                    ->state(fn (Cellule $record): bool => filled($record->whatsapp_lien)),
            ])
            ->filters([
                TernaryFilter::make('archive_le')
                    ->label('Archivage')
                    ->nullable()
                    ->placeholder('Toutes')
                    ->trueLabel('Archivées')
                    ->falseLabel('En activité')
                    ->default(false),
            ])
            ->recordActions([
                EditAction::make(),
                ConservationActions::archiver(fn (Model $record): bool => Gate::allows('archiver', $record)),
                ConservationActions::desarchiver(fn (Model $record): bool => Gate::allows('archiver', $record)),
            ])
            ->defaultSort('nom');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ManageCellules::route('/'),
        ];
    }
}
