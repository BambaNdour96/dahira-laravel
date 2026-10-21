<?php

namespace App\Filament\Resources\TypesProgramme;

use App\Filament\Actions\ConservationActions;
use App\Filament\Resources\TypesProgramme\Pages\ManageTypesProgramme;
use App\Models\TypeProgramme;
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

/** Les types d'événements (Magal, Ziar, Prokhane, Gamou, Laylatoul Qadr…), complétés librement par le super administrateur. */
class TypeProgrammeResource extends Resource
{
    protected static ?string $model = TypeProgramme::class;

    protected static ?string $slug = 'types-evenement';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'type d\'événement';

    protected static ?string $pluralModelLabel = 'types d\'événements';

    protected static ?string $navigationLabel = 'Types d\'événements';

    private static function gere(): bool
    {
        return ($utilisateur = Auth::user()) instanceof User && $utilisateur->can('referentiel.gerer');
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
            TextInput::make('nom')->label('Nom')->required()->maxLength(80)->unique(ignoreRecord: true)->placeholder('Gamou'),
            TextInput::make('ordre')->label('Ordre d\'affichage')->numeric()->default(10),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nom')->label('Type')->searchable()->weight('bold'),
                TextColumn::make('collectes_count')->label('Événements')->counts('collectes'),
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
        return ['index' => ManageTypesProgramme::route('/')];
    }
}
