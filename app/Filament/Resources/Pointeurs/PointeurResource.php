<?php

namespace App\Filament\Resources\Pointeurs;

use App\Enums\StatutMembre;
use App\Filament\Resources\Pointeurs\Pages\ManagePointeurs;
use App\Models\Cellule;
use App\Models\CellulePointeur;
use App\Models\Membre;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/** Les pointeurs désignés de chaque cellule : un Goor Yalla pour les Goor Yalla, une Sokhna pour les Sokhnas. */
class PointeurResource extends Resource
{
    protected static ?string $model = CellulePointeur::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    protected static string|UnitEnum|null $navigationGroup = 'Vie de la Daara';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Pointeurs';

    protected static ?string $modelLabel = 'pointeur';

    protected static ?string $pluralModelLabel = 'pointeurs';

    private static function utilisateur(): ?User
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User ? $utilisateur : null;
    }

    public static function canViewAny(): bool
    {
        return self::utilisateur()?->hasAnyRole(['super_administrateur', 'responsable_section']) ?? false;
    }

    public static function canCreate(): bool
    {
        return self::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        $utilisateur = self::utilisateur();

        return $utilisateur !== null && $record instanceof CellulePointeur
            && ($utilisateur->hasRole('super_administrateur') || Cellule::query()->whereKey($record->cellule_id)->where('responsable_id', $utilisateur->id)->exists());
    }

    /** @return Builder<CellulePointeur> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<CellulePointeur> $query */
        $query = parent::getEloquentQuery();
        $utilisateur = self::utilisateur();

        if ($utilisateur !== null && ! $utilisateur->hasRole('super_administrateur')) {
            $query->whereIn('cellule_id', Cellule::query()->where('responsable_id', $utilisateur->id)->select('id'));
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('cellule_id')
                ->label('Cellule')
                ->options(function (): array {
                    $utilisateur = self::utilisateur();
                    $query = Cellule::query()->orderBy('nom');

                    if ($utilisateur !== null && ! $utilisateur->hasRole('super_administrateur')) {
                        $query->where('responsable_id', $utilisateur->id);
                    }

                    return $query->pluck('nom', 'id')->all();
                })
                ->required()
                ->live(),
            Select::make('user_id')
                ->label('Personne')
                ->searchable()
                ->required()
                ->helperText('Un membre validé de la cellule, qui a un compte.')
                ->options(fn (Get $get): array => Membre::query()
                    ->where('statut', StatutMembre::Valide->value)
                    ->where('cellule_id', $get('cellule_id'))
                    ->whereNotNull('user_id')
                    ->orderBy('nom')
                    ->get()
                    ->mapWithKeys(fn (Membre $membre): array => [(int) $membre->user_id => $membre->prenom.' '.$membre->nom.' — '.$membre->matricule])
                    ->all()),
            Radio::make('perimetre')
                ->label('Qui pointe-t-il ?')
                ->options(CellulePointeur::PERIMETRES)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['cellule', 'user']))
            ->columns([
                TextColumn::make('user.name')->label('Pointeur')->searchable(),
                TextColumn::make('cellule.nom')->label('Cellule'),
                TextColumn::make('perimetre')->label('Pointe')->badge()
                    ->formatStateUsing(fn (string $state): string => CellulePointeur::PERIMETRES[$state] ?? $state),
            ])
            ->recordActions([DeleteAction::make()->label('Retirer')]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManagePointeurs::route('/')];
    }
}
