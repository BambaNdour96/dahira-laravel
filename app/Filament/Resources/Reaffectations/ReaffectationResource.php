<?php

namespace App\Filament\Resources\Reaffectations;

use App\Filament\Resources\Reaffectations\Pages\ListReaffectations;
use App\Models\Collecte;
use App\Models\Projet;
use App\Models\ReaffectationFonds;
use App\Models\User;
use App\Services\ReaffectationService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/** Les réaffectations de fonds : décidées par le Jewrine national, enregistrées par la Finance. */
class ReaffectationResource extends Resource
{
    protected static ?string $model = ReaffectationFonds::class;

    protected static ?string $slug = 'reaffectations';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|UnitEnum|null $navigationGroup = 'Trésorerie';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'réaffectation de fonds';

    protected static ?string $pluralModelLabel = 'réaffectations de fonds';

    protected static ?string $navigationLabel = 'Réaffectations';

    public static function utilisateur(): ?User
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User ? $utilisateur : null;
    }

    public static function canViewAny(): bool
    {
        $utilisateur = self::utilisateur();

        return $utilisateur !== null && ($utilisateur->can('reaffectations.decider') || $utilisateur->can('reaffectations.enregistrer') || $utilisateur->can('finances.voir'));
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
        $attente = self::utilisateur()?->can('reaffectations.enregistrer') === true
            ? ReaffectationFonds::query()->where('statut', ReaffectationFonds::DECIDEE)->count()
            : 0;

        return $attente > 0 ? (string) $attente : null;
    }

    /** @return Builder<ReaffectationFonds> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<ReaffectationFonds> $query */
        $query = parent::getEloquentQuery()->with(['origineCollecte', 'origineProjet', 'destinationCollecte', 'destinationProjet', 'decideur']);

        return $query;
    }

    /**
     * Le choix d'un fonds (origine ou destination).
     *
     * @return list<Select>
     */
    private static function fonds(string $cote, string $libelle): array
    {
        return [
            Select::make($cote)->label($libelle)->required()->live()->options(ReaffectationFonds::FONDS),
            Select::make($cote.'_collecte_id')->label('Programme')->required()->searchable()
                ->options(fn (): array => Collecte::query()->latest('ouverture')->pluck('titre', 'id')->all())
                ->visible(fn (Get $get): bool => $get($cote) === 'programme'),
            Select::make($cote.'_projet_id')->label('Projet')->required()->searchable()
                ->options(fn (): array => Projet::query()->orderBy('titre')->pluck('titre', 'id')->all())
                ->visible(fn (Get $get): bool => $get($cote) === 'projet'),
        ];
    }

    public static function decider(): Action
    {
        return Action::make('decider')
            ->label('Décider une réaffectation')
            ->icon('heroicon-o-plus')
            ->visible(fn (): bool => self::utilisateur()?->can('reaffectations.decider') === true)
            ->modalDescription('L\'argent reste dans ses caisses : il change d\'affectation. La Finance enregistre la réaffectation si le fonds d\'origine a de quoi.')
            ->schema([
                ...self::fonds('origine', 'D\'où vient l\'argent'),
                ...self::fonds('destination', 'Où il va'),
                TextInput::make('montant')->label('Montant (FCFA)')->required()->numeric()->minValue(1),
                Textarea::make('motif')->label('Motif (la décision prise)')->required()->maxLength(255),
            ])
            ->action(fn (array $data) => self::executer(function (ReaffectationService $service, User $auteur) use ($data): void {
                $reaffectation = $service->decider($data, $auteur);
                Notification::make()->title('Réaffectation '.$reaffectation->numero.' décidée : la Finance est prévenue.')->success()->send();
            }));
    }

    /** @param  callable(ReaffectationService, User): void  $operation */
    private static function executer(callable $operation): void
    {
        $auteur = self::utilisateur();
        abort_unless($auteur !== null, 403);

        try {
            $operation(app(ReaffectationService::class), $auteur);
        } catch (DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero')->label('Numéro')->weight('bold')->searchable(),
                TextColumn::make('created_at')->label('Décidée le')->date('d/m/Y')->sortable(),
                TextColumn::make('origine')->label('Origine')->state(fn (ReaffectationFonds $record): string => $record->libelleOrigine()),
                TextColumn::make('destination')->label('Destination')->state(fn (ReaffectationFonds $record): string => $record->libelleDestination()),
                TextColumn::make('montant')->label('Montant')->formatStateUsing(fn (int $state): string => number_format($state, 0, ',', ' ').' FCFA')->alignEnd(),
                TextColumn::make('motif')->label('Motif')->limit(40),
                TextColumn::make('statut')->label('État')->badge()
                    ->formatStateUsing(fn (string $state): string => ReaffectationFonds::LIBELLES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        ReaffectationFonds::ENREGISTREE => 'success',
                        ReaffectationFonds::ANNULEE => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('statut')->label('État')->options(ReaffectationFonds::LIBELLES),
            ])
            ->recordActions([
                Action::make('enregistrer')->label('Enregistrer')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (ReaffectationFonds $record): bool => ($utilisateur = self::utilisateur()) !== null && app(ReaffectationService::class)->peutEnregistrer($utilisateur, $record))
                    ->requiresConfirmation()
                    ->modalDescription(fn (ReaffectationFonds $record): string => number_format($record->montant, 0, ',', ' ').' FCFA · '.$record->libelleOrigine().' → '.$record->libelleDestination())
                    ->action(fn (ReaffectationFonds $record) => self::executer(function (ReaffectationService $service, User $auteur) use ($record): void {
                        $service->enregistrer($record, $auteur);
                        Notification::make()->title('Réaffectation enregistrée.')->success()->send();
                    })),
                Action::make('annuler')->label('Annuler')->icon('heroicon-o-arrow-uturn-left')->color('gray')
                    ->visible(fn (ReaffectationFonds $record): bool => ($utilisateur = self::utilisateur()) !== null && app(ReaffectationService::class)->peutAnnuler($utilisateur, $record))
                    ->requiresConfirmation()
                    ->action(fn (ReaffectationFonds $record) => self::executer(fn (ReaffectationService $service, User $auteur) => $service->annuler($record, $auteur))),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListReaffectations::route('/')];
    }
}
