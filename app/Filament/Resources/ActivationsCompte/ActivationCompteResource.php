<?php

namespace App\Filament\Resources\ActivationsCompte;

use App\Filament\Resources\ActivationsCompte\Pages\ListActivationsCompte;
use App\Models\ActivationCompte;
use App\Models\User;
use App\Services\ActivationCompteService;
use App\Services\CasquettesService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
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

/** Les demandes d'activation de compte sans matricule : le Jewrine de cellule vérifie l'identité et valide. */
class ActivationCompteResource extends Resource
{
    protected static ?string $model = ActivationCompte::class;

    protected static ?string $slug = 'activations-compte';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|UnitEnum|null $navigationGroup = 'Registre';

    protected static ?int $navigationSort = 8;

    protected static ?string $modelLabel = 'activation de compte';

    protected static ?string $pluralModelLabel = 'activations de compte';

    protected static ?string $navigationLabel = 'Activations de compte';

    private static function utilisateur(): ?User
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User ? $utilisateur : null;
    }

    public static function canViewAny(): bool
    {
        $utilisateur = self::utilisateur();

        return $utilisateur !== null && app(CasquettesService::class)->peutQuelquePart($utilisateur, 'membres.valider');
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
        $attente = self::canViewAny() ? self::getEloquentQuery()->where('statut', ActivationCompte::EN_ATTENTE)->count() : 0;

        return $attente > 0 ? (string) $attente : null;
    }

    /** @return Builder<ActivationCompte> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<ActivationCompte> $query */
        $query = parent::getEloquentQuery()->with('membre.cellule');
        $utilisateur = self::utilisateur();

        if ($utilisateur !== null && ! $utilisateur->can('membres.valider')) {
            $cellules = app(CasquettesService::class)->cellulesDirigees($utilisateur);
            $query->whereHas('membre', fn (Builder $membre) => $membre->whereIn('cellule_id', $cellules));
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Demandé le')->dateTime('d/m/Y H:i'),
                TextColumn::make('membre.nom_complet')->label('Membre'),
                TextColumn::make('membre.cellule.nom')->label('Cellule'),
                TextColumn::make('telephone')->label('Téléphone'),
                TextColumn::make('membre.date_naissance')->label('Né(e) le')->date('d/m/Y'),
                TextColumn::make('statut')->label('État')->badge()
                    ->formatStateUsing(fn (string $state): string => ActivationCompte::LIBELLES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        ActivationCompte::VALIDEE => 'success',
                        ActivationCompte::REFUSEE => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([SelectFilter::make('statut')->label('État')->options(ActivationCompte::LIBELLES)->default(ActivationCompte::EN_ATTENTE)])
            ->recordActions([
                self::decision('valider', 'Valider', 'success', 'heroicon-o-check', 'Appelez le membre ou voyez-le pour vérifier que c\'est bien lui. Son compte s\'ouvre avec le mot de passe qu\'il a choisi.'),
                self::decision('refuser', 'Refuser', 'danger', 'heroicon-o-x-mark', 'La demande est écartée ; le membre pourra en refaire une.'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function decision(string $nom, string $libelle, string $couleur, string $icone, string $description): Action
    {
        return Action::make($nom)
            ->label($libelle)
            ->color($couleur)
            ->icon($icone)
            ->visible(fn (ActivationCompte $record): bool => $record->statut === ActivationCompte::EN_ATTENTE
                && ($utilisateur = self::utilisateur()) !== null && app(ActivationCompteService::class)->peutDecider($utilisateur, $record))
            ->requiresConfirmation()
            ->modalDescription($description)
            ->action(function (ActivationCompte $record) use ($nom): void {
                $auteur = self::utilisateur();
                abort_unless($auteur !== null, 403);

                try {
                    $nom === 'valider'
                        ? app(ActivationCompteService::class)->valider($record, $auteur)
                        : app(ActivationCompteService::class)->refuser($record, $auteur);
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title($nom === 'valider' ? 'Compte activé : le membre peut se connecter.' : 'Demande refusée.')->success()->send();
            });
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListActivationsCompte::route('/')];
    }
}
