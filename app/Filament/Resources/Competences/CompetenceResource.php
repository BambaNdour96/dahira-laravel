<?php

namespace App\Filament\Resources\Competences;

use App\Filament\Actions\ConservationActions;
use App\Filament\Resources\Competences\Pages\ManageCompetences;
use App\Models\Competence;
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

/** Les savoir-faire que les membres peuvent indiquer : une liste tenue dans la plateforme, sans toucher au code. */
class CompetenceResource extends Resource
{
    protected static ?string $model = Competence::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string|UnitEnum|null $navigationGroup = 'Registre';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'savoir-faire';

    protected static ?string $pluralModelLabel = 'savoir-faire';

    protected static ?string $navigationLabel = 'Savoir-faire';

    protected static ?string $recordTitleAttribute = 'nom';

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

    /** Rien ne se supprime : un savoir-faire qui n'a plus cours s'archive. */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nom')->label('Savoir-faire')->required()->maxLength(100)->unique(ignoreRecord: true),
            TextInput::make('ordre')->label('Ordre d\'affichage')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nom')->label('Savoir-faire')->searchable()->sortable(),
                TextColumn::make('membres_count')->label('Membres')->counts('membres')->sortable(),
                TextColumn::make('archive_le')->label('Archivé le')->date('d/m/Y')->placeholder('—')->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('archive_le')->label('Archivage')->nullable()->placeholder('Tous')
                    ->trueLabel('Archivés')->falseLabel('En activité')->default(false),
            ])
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
        return ['index' => ManageCompetences::route('/')];
    }
}
