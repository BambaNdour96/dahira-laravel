<?php

namespace App\Filament\Resources\ComptesBureau;

use App\Filament\Actions\EnTantQueAction;
use App\Filament\Resources\ComptesBureau\Pages\ManageComptesBureau;
use App\Models\User;
use App\Services\CasquettesService;
use App\Services\CompteService;
use App\Support\AffichageMotDePasse;
use App\Support\Capacites;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Les comptes des responsables, pour le super administrateur : casquettes en cours, activation,
 * mot de passe provisoire, « voir en tant que ». Les droits se règlent dans « Nominations ».
 */
class CompteBureauResource extends Resource
{
    protected static ?string $model = User::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'comptes';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'compte';

    protected static ?string $pluralModelLabel = 'comptes';

    protected static ?string $navigationLabel = 'Comptes';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->whereHas('roles', fn (Builder $roles) => $roles->where('name', '!=', 'membre'))
                ->with(['roles', 'membre']))
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('telephone')->label('Téléphone')->searchable(),
                TextColumn::make('membre.matricule')->label('Matricule')->placeholder('—'),
                TextColumn::make('casquettes')
                    ->label('Casquettes')
                    ->badge()
                    ->state(fn (User $record): array => $record->getRoleNames()
                        ->diff(['membre'])
                        ->map(fn (string $role): string => Capacites::CASQUETTES[$role] ?? $role)
                        ->values()
                        ->all()),
                IconColumn::make('actif')->label('Actif')->boolean(),
                TextColumn::make('derniere_connexion_at')->label('Dernière connexion')->since()->placeholder('Jamais'),
            ])
            ->recordActions([
                EnTantQueAction::pourCompte(),

                Action::make('changerEtat')
                    ->label(fn (User $record): string => $record->actif ? 'Désactiver' : 'Réactiver')
                    ->icon(fn (User $record): string => $record->actif ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
                    ->color(fn (User $record): string => $record->actif ? 'danger' : 'success')
                    ->hidden(fn (User $record): bool => CasquettesService::estSuperAdministrateur($record))
                    ->requiresConfirmation()
                    ->modalDescription(fn (User $record): string => $record->actif
                        ? 'La personne ne pourra plus se connecter, ni au panneau ni à son compte de membre. Ses nominations ne changent pas.'
                        : 'La personne pourra de nouveau se connecter.')
                    ->action(function (User $record): void {
                        try {
                            app(CompteService::class)->changerEtat($record, ! $record->actif);
                        } catch (DomainException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title($record->actif ? 'Compte réactivé.' : 'Compte désactivé.')->success()->send();
                    }),

                Action::make('reinitialiserMotDePasse')
                    ->label('Nouveau mot de passe')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Un nouveau mot de passe provisoire sera généré. L\'ancien ne fonctionnera plus.')
                    ->action(function (User $record): void {
                        $motDePasse = app(CompteService::class)->reinitialiserMotDePasse($record);

                        Notification::make()
                            ->title('Nouveau mot de passe de '.$record->name)
                            ->body(AffichageMotDePasse::corps($motDePasse))
                            ->success()
                            ->persistent()
                            ->send();
                    }),
            ])
            ->defaultSort('name');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ManageComptesBureau::route('/'),
        ];
    }
}
