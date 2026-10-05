<?php

namespace App\Filament\Resources\Exercices;

use App\Filament\Resources\Exercices\Pages\ManageExercices;
use App\Models\Exercice;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ExerciceResource extends Resource
{
    protected static ?string $model = Exercice::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'exercice';

    protected static ?string $pluralModelLabel = 'exercices';

    protected static ?string $recordTitleAttribute = 'libelle';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('libelle')
                ->label('Libellé')
                ->required()
                ->maxLength(20)
                ->unique(ignoreRecord: true)
                ->placeholder('2026'),
            DatePicker::make('date_debut')
                ->label('Début')
                ->required(),
            DatePicker::make('date_fin')
                ->label('Fin')
                ->required()
                ->after('date_debut'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('libelle')->label('Libellé')->sortable(),
                TextColumn::make('date_debut')->label('Début')->date('d/m/Y')->sortable(),
                TextColumn::make('date_fin')->label('Fin')->date('d/m/Y'),
                IconColumn::make('en_cours')
                    ->label('En cours')
                    ->boolean()
                    ->state(fn (Exercice $record): bool => Exercice::enCours()?->is($record) ?? false),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('date_debut', 'desc');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ManageExercices::route('/'),
        ];
    }
}
