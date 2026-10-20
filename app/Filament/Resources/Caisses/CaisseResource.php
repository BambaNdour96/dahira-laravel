<?php

namespace App\Filament\Resources\Caisses;

use App\Enums\TypeCaisse;
use App\Filament\Actions\ConservationActions;
use App\Filament\Resources\Caisses\Pages\ListCaisses;
use App\Filament\Resources\Caisses\Pages\ViewCaisse;
use App\Models\Caisse;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
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

/**
 * Les caisses de la Daara et leur solde. Le super administrateur crée et archive les caisses (une banque, plus tard,
 * sans toucher au code) ; les caisses des représentants se créent à leur désignation.
 */
class CaisseResource extends Resource
{
    protected static ?string $model = Caisse::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Trésorerie';

    protected static ?int $navigationSort = 0;

    protected static ?string $modelLabel = 'caisse';

    protected static ?string $pluralModelLabel = 'caisses';

    protected static ?string $recordTitleAttribute = 'nom';

    private static function utilisateur(): ?User
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User ? $utilisateur : null;
    }

    private static function gere(): bool
    {
        return self::utilisateur()?->can('referentiel.gerer') === true;
    }

    public static function canViewAny(): bool
    {
        return self::utilisateur()?->can('finances.voir') === true || self::gere();
    }

    public static function canView(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function canCreate(): bool
    {
        return self::gere();
    }

    public static function canEdit(Model $record): bool
    {
        return self::gere() && $record instanceof Caisse && $record->type !== TypeCaisse::Representant;
    }

    /** Rien ne se supprime : une caisse qui n'a plus cours s'archive. */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nom')->label('Nom')->required()->maxLength(100)->unique(ignoreRecord: true),
            Select::make('type')->label('Type')->required()
                ->options(collect(TypeCaisse::cases())->reject(fn (TypeCaisse $type): bool => $type === TypeCaisse::Representant)->mapWithKeys(fn (TypeCaisse $type): array => [$type->value => $type->getLabel()])->all())
                ->helperText('Les caisses des représentants se créent toutes seules, à leur désignation.'),
            TextInput::make('ordre')->label('Ordre d\'affichage')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nom')->label('Caisse')->weight('bold')->searchable(),
                TextColumn::make('type')->label('Type')->badge(),
                TextColumn::make('solde')->label('Solde')->state(fn (Caisse $record): string => number_format($record->solde(), 0, ',', ' ').' FCFA')->alignEnd(),
                TextColumn::make('archive_le')->label('Archivée le')->date('d/m/Y')->placeholder('—')->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('archive_le')->label('Archivage')->nullable()->placeholder('Toutes')->trueLabel('Archivées')->falseLabel('En activité')->default(false),
            ])
            ->recordActions([
                ViewAction::make()->label('Mouvements'),
                EditAction::make(),
                ConservationActions::archiver(fn (Model $record): bool => self::gere() && $record instanceof Caisse && $record->solde() === 0),
                ConservationActions::desarchiver(fn (Model $record): bool => self::gere()),
            ])
            ->defaultSort('ordre');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListCaisses::route('/'),
            'view' => ViewCaisse::route('/{record}'),
        ];
    }
}
