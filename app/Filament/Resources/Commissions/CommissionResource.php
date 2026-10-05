<?php

namespace App\Filament\Resources\Commissions;

use App\Filament\Resources\Commissions\Pages\ManageCommissions;
use App\Models\Commission;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CommissionResource extends Resource
{
    protected static ?string $model = Commission::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static string|UnitEnum|null $navigationGroup = 'Registre';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'commission';

    protected static ?string $pluralModelLabel = 'commissions';

    protected static ?string $recordTitleAttribute = 'nom';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nom')
                ->label('Nom')
                ->required()
                ->maxLength(100)
                ->unique(ignoreRecord: true),
            Textarea::make('description')
                ->label('Description')
                ->rows(3),
            Toggle::make('actif')
                ->label('Proposée à l\'inscription')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nom')->label('Nom')->searchable()->sortable(),
                TextColumn::make('membres_count')->label('Membres')->counts('membres')->sortable(),
                IconColumn::make('actif')->label('Proposée')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('nom');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ManageCommissions::route('/'),
        ];
    }
}
