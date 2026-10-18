<?php

namespace App\Filament\Resources\ChangementsCellule;

use App\Filament\Resources\ChangementsCellule\Pages\ListChangementsCellule;
use App\Models\ChangementCellule;
use App\Models\User;
use App\Services\CasquettesService;
use App\Services\ChangementCelluleService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/** Les demandes de changement de cellule : le super administrateur valide ou refuse ; le Jewrine suit celles de sa cellule. */
class ChangementCelluleResource extends Resource
{
    protected static ?string $model = ChangementCellule::class;

    protected static ?string $slug = 'changements-cellule';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|UnitEnum|null $navigationGroup = 'Registre';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'changement de cellule';

    protected static ?string $pluralModelLabel = 'changements de cellule';

    protected static ?string $navigationLabel = 'Changements de cellule';

    private static function utilisateur(): ?User
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User ? $utilisateur : null;
    }

    private static function decide(): bool
    {
        return ($utilisateur = self::utilisateur()) !== null && $utilisateur->can('membres.changer_cellule');
    }

    public static function canViewAny(): bool
    {
        $utilisateur = self::utilisateur();

        return $utilisateur !== null && (self::decide() || app(CasquettesService::class)->peutQuelquePart($utilisateur, 'membres.modifier'));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $attente = self::decide() ? ChangementCellule::query()->where('statut', ChangementCellule::EN_ATTENTE)->count() : 0;

        return $attente > 0 ? (string) $attente : null;
    }

    /** @return Builder<ChangementCellule> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<ChangementCellule> $query */
        $query = parent::getEloquentQuery()->with(['membre', 'depart', 'arrivee', 'demandeur']);
        $utilisateur = self::utilisateur();

        // Le Jewrine voit les demandes qui touchent ses cellules (départ ou arrivée).
        if ($utilisateur !== null && ! self::decide() && ! $utilisateur->can('membres.modifier')) {
            $cellules = app(CasquettesService::class)->cellulesDirigees($utilisateur);
            $query->where(fn (Builder $sous) => $sous->whereIn('cellule_depart_id', $cellules)->orWhereIn('cellule_arrivee_id', $cellules));
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Demandé le')->date('d/m/Y')->sortable(),
                TextColumn::make('membre.nom_complet')->label('Membre'),
                TextColumn::make('depart.nom')->label('De'),
                TextColumn::make('arrivee.nom')->label('Vers'),
                TextColumn::make('demandeur.name')->label('Demandé par'),
                TextColumn::make('motif')->label('Motif')->placeholder('—')->limit(40),
                TextColumn::make('statut')->label('État')->badge()
                    ->formatStateUsing(fn (string $state): string => ChangementCellule::LIBELLES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        ChangementCellule::EN_ATTENTE => 'warning',
                        ChangementCellule::VALIDE => 'success',
                        ChangementCellule::REFUSE => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('statut')->label('État')->options(ChangementCellule::LIBELLES)->default(ChangementCellule::EN_ATTENTE),
            ])
            ->recordActions([self::valider(), self::refuser()])
            ->defaultSort('created_at', 'desc');
    }

    public static function valider(): Action
    {
        return Action::make('valider')
            ->label('Valider')
            ->icon('heroicon-o-check')
            ->color('success')
            ->visible(fn (ChangementCellule $record): bool => self::decide() && $record->statut === ChangementCellule::EN_ATTENTE)
            ->requiresConfirmation()
            ->modalHeading(fn (ChangementCellule $record): string => $record->membre->nom_complet.' : '.$record->depart->nom.' → '.$record->arrivee->nom)
            ->modalDescription(fn (ChangementCellule $record): string => app(ChangementCelluleService::class)->avertissement($record))
            ->modalSubmitActionLabel('Valider le changement')
            ->action(function (ChangementCellule $record): void {
                $auteur = self::utilisateur();
                abort_unless($auteur !== null, 403);

                try {
                    app(ChangementCelluleService::class)->valider($record, $auteur);
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Changement de cellule validé. Les intéressés sont prévenus.')->success()->send();
            });
    }

    public static function refuser(): Action
    {
        return Action::make('refuser')
            ->label('Refuser')
            ->icon('heroicon-o-x-mark')
            ->color('danger')
            ->visible(fn (ChangementCellule $record): bool => self::decide() && $record->statut === ChangementCellule::EN_ATTENTE)
            ->schema([Textarea::make('motif')->label('Motif du refus')->required()->maxLength(255)])
            ->action(function (ChangementCellule $record, array $data): void {
                $auteur = self::utilisateur();
                abort_unless($auteur !== null, 403);

                try {
                    app(ChangementCelluleService::class)->refuser($record, (string) $data['motif'], $auteur);
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Demande refusée. Le membre est prévenu.')->success()->send();
            });
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListChangementsCellule::route('/')];
    }
}
