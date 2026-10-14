<?php

namespace App\Filament\Resources\Dahiras;

use App\Filament\Actions\ConservationActions;
use App\Filament\Resources\Dahiras\Pages\CreateDahira;
use App\Filament\Resources\Dahiras\Pages\EditDahira;
use App\Filament\Resources\Dahiras\Pages\ListDahiras;
use App\Filament\Resources\Dahiras\Pages\ViewDahira;
use App\Models\Cellule;
use App\Models\Evenement;
use App\Models\User;
use App\Services\CasquettesService;
use App\Services\ConservationService;
use App\Services\PointageService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Les dahiras des cellules, créées par leurs Jewrine quand elles ont lieu.
 * Chaque cellule ne voit que ses dahiras ; le bureau et Suivi et Évaluation voient tout.
 */
class DahiraResource extends Resource
{
    protected static ?string $model = Evenement::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'Vie de la Daara';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Dahiras';

    protected static ?string $modelLabel = 'dahira';

    protected static ?string $pluralModelLabel = 'dahiras';

    private static function utilisateur(): ?User
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User ? $utilisateur : null;
    }

    public static function canViewAny(): bool
    {
        return ($utilisateur = self::utilisateur()) !== null && app(CasquettesService::class)->peutQuelquePart($utilisateur, 'dahiras.voir');
    }

    public static function canCreate(): bool
    {
        return ($utilisateur = self::utilisateur()) !== null && app(CasquettesService::class)->peutQuelquePart($utilisateur, 'dahiras.gerer');
    }

    public static function canEdit(Model $record): bool
    {
        $utilisateur = self::utilisateur();

        if ($utilisateur === null || ! $record instanceof Evenement) {
            return false;
        }

        return app(CasquettesService::class)->peut($utilisateur, 'dahiras.gerer', $record->cellule_id);
    }

    public static function canView(Model $record): bool
    {
        $utilisateur = self::utilisateur();

        if ($utilisateur === null || ! $record instanceof Evenement) {
            return false;
        }

        return app(CasquettesService::class)->peut($utilisateur, 'dahiras.voir', $record->cellule_id);
    }

    /** Rien ne se supprime : une dahira s'annule, avec un motif (voir annuler). */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function annuler(): Action
    {
        return ConservationActions::annuler(
            fn (Model $record): bool => self::canEdit($record),
            function (Model $record, string $motif, User $auteur): void {
                if ($record instanceof Evenement) {
                    app(ConservationService::class)->annulerEvenement($record, $motif, $auteur);
                }
            },
            'La dahira reste dans l\'historique, avec son motif, et ne compte pas dans l\'assiduité. Si le rappel du jour est parti, les membres sont prévenus.',
        );
    }

    /** Un Jewrine ne voit que les dahiras de sa cellule. @return Builder<Evenement> */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->where('type', Evenement::DAHIRA);
        $utilisateur = self::utilisateur();

        if ($utilisateur !== null && ! $utilisateur->can('dahiras.voir')) {
            $query->whereIn('cellule_id', app(CasquettesService::class)->cellulesDirigees($utilisateur));
        }

        return $query;
    }

    /** @return array<int|string, string> */
    public static function cellulesPermises(): array
    {
        $utilisateur = self::utilisateur();
        $query = Cellule::query()->enActivite()->orderBy('nom');

        if ($utilisateur !== null && ! $utilisateur->can('dahiras.gerer')) {
            $query->whereIn('id', app(CasquettesService::class)->cellulesDirigees($utilisateur));
        }

        return $query->pluck('nom', 'id')->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('cellule_id')
                ->label('Cellule')
                ->options(fn (): array => self::cellulesPermises())
                ->default(fn (): mixed => array_key_first(self::cellulesPermises()))
                ->required(),
            TextInput::make('lieu')->label('Lieu')->required()->maxLength(150)->placeholder('Exemple : chez Serigne Fall, Médina'),
            DateTimePicker::make('debut')->label('Date et heure')->required()->native(false)->seconds(false),
            DateTimePicker::make('fin')->label('Fin (facultatif)')->native(false)->seconds(false)->after('debut'),
            Textarea::make('description')->label('Thème ou programme')->rows(3)->maxLength(500)
                ->placeholder('Exemple : récital de Xassaïdes, causerie sur le Mawlid')
                ->columnSpanFull(),
            Toggle::make('avec_rappel')->label('Rappeler la dahira aux membres de la cellule le jour même')->default(true)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('cellule')->withCount(['presents']))
            ->columns([
                TextColumn::make('debut')->label('Date')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('cellule.nom')->label('Cellule'),
                TextColumn::make('lieu')->label('Lieu')->limit(30),
                TextColumn::make('description')->label('Programme')->limit(40)->placeholder('—'),
                TextColumn::make('presents_count')->label('Présents'),
                TextColumn::make('annule_le')->label('')->badge()->color('danger')
                    ->formatStateUsing(fn (): string => 'Annulée')
                    ->tooltip(fn (Evenement $record): ?string => $record->motif_annulation),
                TextColumn::make('taux')->label('Présence')->badge()
                    ->state(fn (Evenement $record): string => app(PointageService::class)->bilan($record)['taux'].' %')
                    ->color(fn (string $state): string => (int) $state >= 75 ? 'success' : ((int) $state >= 40 ? 'warning' : 'gray')),
            ])
            ->filters([
                SelectFilter::make('cellule_id')->label('Cellule')->options(fn (): array => Cellule::query()->orderBy('nom')->pluck('nom', 'id')->all()),
            ])
            ->recordActions([ViewAction::make()->label('Présences'), EditAction::make(), self::annuler()])
            ->defaultSort('debut', 'desc');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListDahiras::route('/'),
            'create' => CreateDahira::route('/create'),
            'view' => ViewDahira::route('/{record}'),
            'edit' => EditDahira::route('/{record}/edit'),
        ];
    }
}
