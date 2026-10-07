<?php

namespace App\Filament\Resources\DemandesSuppression;

use App\Filament\Resources\DemandesSuppression\Pages\ManageDemandesSuppression;
use App\Models\DemandeSuppression;
use App\Models\JournalActivite;
use App\Models\User;
use App\Services\DonneesPersonnellesService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Demandes d'effacement des données, réservées au super administrateur.
 */
class DemandeSuppressionResource extends Resource
{
    protected static ?string $model = DemandeSuppression::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'demandes-suppression';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-minus';

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'demande de suppression';

    protected static ?string $pluralModelLabel = 'demandes de suppression';

    protected static ?string $navigationLabel = 'Demandes de suppression';

    public static function canViewAny(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User && $utilisateur->hasRole('super_administrateur');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $enAttente = DemandeSuppression::query()->where('statut', DemandeSuppression::EN_ATTENTE)->count();

        return $enAttente > 0 ? (string) $enAttente : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['membre', 'user']))
            ->columns([
                TextColumn::make('created_at')->label('Reçue le')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('personne')
                    ->label('Personne')
                    ->state(fn (DemandeSuppression $record): string => $record->membre !== null
                        ? $record->membre->prenom.' '.$record->membre->nom
                        : ($record->user->name ?? '—')),
                TextColumn::make('membre.matricule')->label('Matricule')->placeholder('—'),
                TextColumn::make('motif')->label('Motif')->limit(60)->placeholder('—'),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => DemandeSuppression::libelle($state))
                    ->color(fn (string $state): string => match ($state) {
                        DemandeSuppression::EN_ATTENTE => 'warning',
                        DemandeSuppression::TRAITEE => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('traitee_le')->label('Traitée le')->dateTime('d/m/Y')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('anonymiser')
                    ->label('Effacer les données')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (DemandeSuppression $record): bool => $record->statut === DemandeSuppression::EN_ATTENTE)
                    ->requiresConfirmation()
                    ->modalDescription('Les données personnelles seront effacées définitivement. Les paiements restent dans la comptabilité, sans nom. Cette action est irréversible.')
                    ->action(function (DemandeSuppression $record): void {
                        if (! self::canViewAny()) {
                            return;
                        }

                        $auteur = Auth::user();
                        app(DonneesPersonnellesService::class)->traiterDemande($record, $auteur instanceof User ? $auteur : null);

                        Notification::make()->title('Données personnelles effacées.')->success()->send();
                    }),
                Action::make('refuserDemande')
                    ->label('Refuser')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->visible(fn (DemandeSuppression $record): bool => $record->statut === DemandeSuppression::EN_ATTENTE)
                    ->schema([
                        Textarea::make('reponse')->label('Motif du refus')->required()->maxLength(1000),
                    ])
                    ->action(function (DemandeSuppression $record, array $data): void {
                        if (! self::canViewAny()) {
                            return;
                        }

                        $record->update([
                            'statut' => DemandeSuppression::REFUSEE,
                            'reponse' => (string) $data['reponse'],
                            'traitee_par' => Auth::id(),
                            'traitee_le' => now(),
                        ]);

                        JournalActivite::enregistrer($record, 'refus_suppression');

                        Notification::make()->title('Demande refusée.')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ManageDemandesSuppression::route('/'),
        ];
    }
}
