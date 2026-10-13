<?php

namespace App\Filament\Resources\RepresentantsFinance;

use App\Enums\Sexe;
use App\Enums\StatutMembre;
use App\Filament\Resources\RepresentantsFinance\Pages\ManageRepresentantsFinance;
use App\Models\Cellule;
use App\Models\Membre;
use App\Models\RemiseFonds;
use App\Models\RepresentantFinance;
use App\Models\User;
use App\Services\CollecteService;
use App\Services\RepresentantFinanceService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use UnitEnum;

/**
 * Les représentants du Jewrine finance dans les cellules, et la supervision de leurs caisses :
 * en caisse, à valider, dernière remise ; réception des remises et bordereaux.
 */
class RepresentantFinanceResource extends Resource
{
    protected static ?string $model = RepresentantFinance::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static string|UnitEnum|null $navigationGroup = 'Trésorerie';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Représentants finance';

    protected static ?string $modelLabel = 'représentant';

    protected static ?string $pluralModelLabel = 'représentants finance';

    public static function canViewAny(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User && $utilisateur->hasAnyRole(['tresorier', 'super_administrateur', 'jewrine_national']);
    }

    public static function peutGerer(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User && $utilisateur->hasAnyRole(['tresorier', 'super_administrateur']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $service = app(RepresentantFinanceService::class);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['cellule', 'user']))
            ->columns([
                TextColumn::make('cellule.nom')->label('Cellule')->sortable(),
                TextColumn::make('perimetre')->label('Représente')->badge()
                    ->formatStateUsing(fn (string $state): string => RepresentantFinance::PERIMETRES[$state] ?? $state),
                TextColumn::make('user.name')->label('Représentant'),
                TextColumn::make('en_caisse')->label('En caisse')->weight('bold')
                    ->state(fn (RepresentantFinance $record): string => CollecteService::fcfa($service->enCaisse($record->user))),
                TextColumn::make('a_valider')->label('À valider')->badge()->color('warning')
                    ->state(fn (RepresentantFinance $record): int => $service->aValider($record->user)->count()),
                TextColumn::make('derniere_remise')->label('Dernière remise')->placeholder('—')
                    ->state(fn (RepresentantFinance $record): ?string => RemiseFonds::query()
                        ->where('representant_id', $record->user_id)->latest('created_at')->value('created_at')?->format('d/m/Y')),
            ])
            ->headerActions([self::designer()])
            ->recordActions([self::remise(), self::bordereaux(), self::retirer()])
            ->defaultSort('cellule_id');
    }

    private static function designer(): Action
    {
        return Action::make('designer')
            ->label('Désigner un représentant')
            ->icon('heroicon-o-user-plus')
            ->visible(fn (): bool => self::peutGerer())
            ->schema([
                Select::make('cellule_id')
                    ->label('Cellule')
                    ->options(fn (): array => Cellule::query()->orderBy('nom')->pluck('nom', 'id')->all())
                    ->required()
                    ->live(),
                Radio::make('perimetre')
                    ->label('Il représente')
                    ->options(RepresentantFinance::PERIMETRES)
                    ->required()
                    ->live(),
                Select::make('user_id')
                    ->label('Représentant')
                    ->searchable()
                    ->required()
                    ->helperText('Un Goor Yalla pour les Goor Yalla, une Sokhna pour les Sokhnas, membre validé de la cellule.')
                    ->options(fn (Get $get): array => Membre::query()
                        ->where('statut', StatutMembre::Valide->value)
                        ->where('cellule_id', $get('cellule_id'))
                        ->where('sexe', $get('perimetre') === 'femmes' ? Sexe::Femme->value : Sexe::Homme->value)
                        ->whereNotNull('user_id')
                        ->orderBy('nom')
                        ->get()
                        ->mapWithKeys(fn (Membre $membre): array => [(int) $membre->user_id => $membre->prenom.' '.$membre->nom.' — '.$membre->matricule])
                        ->all()),
            ])
            ->action(function (array $data): void {
                $auteur = Auth::user();
                abort_unless($auteur instanceof User, 403);

                try {
                    app(RepresentantFinanceService::class)->designer(
                        Cellule::query()->findOrFail((int) $data['cellule_id']),
                        (string) $data['perimetre'],
                        User::query()->findOrFail((int) $data['user_id']),
                        $auteur,
                    );
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Représentant désigné : il retrouve sa caisse dans son menu.')->success()->send();
            });
    }

    private static function remise(): Action
    {
        return Action::make('remise')
            ->label('Remise reçue')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (RepresentantFinance $record): bool => self::peutGerer()
                && app(RepresentantFinanceService::class)->enCaisse($record->user) > 0)
            ->requiresConfirmation()
            ->modalHeading('Recevoir les espèces du représentant')
            ->modalDescription(fn (RepresentantFinance $record): string => 'Vous confirmez avoir reçu '
                .CollecteService::fcfa(app(RepresentantFinanceService::class)->enCaisse($record->user))
                .' de '.$record->user->name.'. Sa caisse reviendra à zéro, et le bordereau sera téléchargé.')
            ->modalSubmitActionLabel('J\'ai reçu les espèces')
            ->action(function (RepresentantFinance $record, Component $livewire): void {
                $receveur = Auth::user();
                abort_unless($receveur instanceof User, 403);

                try {
                    $remise = app(RepresentantFinanceService::class)->recevoirRemise($record->user, $receveur);
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Remise '.$remise->numero.' enregistrée.')->success()->send();
                $livewire->redirect(route('remises.bordereau', $remise));
            });
    }

    private static function bordereaux(): Action
    {
        return Action::make('bordereaux')
            ->label('Remises')
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->modalHeading(fn (RepresentantFinance $record): string => 'Remises de '.$record->user->name)
            ->modalContent(fn (RepresentantFinance $record) => view('filament.remises-representant', [
                'remises' => RemiseFonds::query()->where('representant_id', $record->user_id)->latest('created_at')->limit(20)->get(),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fermer');
    }

    private static function retirer(): Action
    {
        return Action::make('retirer')
            ->label('Retirer')
            ->icon('heroicon-o-user-minus')
            ->color('danger')
            ->visible(fn (): bool => self::peutGerer())
            ->requiresConfirmation()
            ->action(function (RepresentantFinance $record): void {
                try {
                    app(RepresentantFinanceService::class)->retirer($record);
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Représentant retiré.')->success()->send();
            });
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageRepresentantsFinance::route('/')];
    }
}
