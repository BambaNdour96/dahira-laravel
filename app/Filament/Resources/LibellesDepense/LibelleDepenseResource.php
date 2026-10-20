<?php

namespace App\Filament\Resources\LibellesDepense;

use App\Filament\Actions\ConservationActions;
use App\Filament\Resources\LibellesDepense\Pages\ManageLibellesDepense;
use App\Models\LibelleDepense;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/** Les libellés de dépenses, créés librement par la Finance ; ils s'archivent. */
class LibelleDepenseResource extends Resource
{
    protected static ?string $model = LibelleDepense::class;

    protected static ?string $slug = 'libelles-depense';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|UnitEnum|null $navigationGroup = 'Trésorerie';

    protected static ?int $navigationSort = 9;

    protected static ?string $modelLabel = 'libellé de dépense';

    protected static ?string $pluralModelLabel = 'libellés de dépenses';

    protected static ?string $navigationLabel = 'Libellés de dépenses';

    private static function gere(): bool
    {
        return ($utilisateur = Auth::user()) instanceof User && $utilisateur->can('depenses.libelles');
    }

    public static function canViewAny(): bool
    {
        return self::gere();
    }

    public static function canCreate(): bool
    {
        return self::gere();
    }

    public static function canEdit(Model $record): bool
    {
        return self::gere();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nom')->label('Libellé')->required()->maxLength(100)->unique(ignoreRecord: true),
            TextInput::make('ordre')->label('Ordre d\'affichage')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nom')->label('Libellé')->searchable(),
                TextColumn::make('archive_le')->label('Archivé le')->date('d/m/Y')->placeholder('—'),
            ])
            ->filters([TernaryFilter::make('archive_le')->label('Archivage')->nullable()->placeholder('Tous')->trueLabel('Archivés')->falseLabel('En activité')->default(false)])
            ->recordActions([
                EditAction::make(),
                ConservationActions::archiver(fn (Model $record): bool => self::gere()),
                ConservationActions::desarchiver(fn (Model $record): bool => self::gere()),
            ])
            ->defaultSort('ordre');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageLibellesDepense::route('/')];
    }
}
