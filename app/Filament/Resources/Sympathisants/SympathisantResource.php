<?php

namespace App\Filament\Resources\Sympathisants;

use App\Enums\MoyenPaiement;
use App\Enums\Sexe;
use App\Enums\StatutCollecte;
use App\Filament\Resources\Sympathisants\Pages\ManageSympathisants;
use App\Models\Cellule;
use App\Models\Collecte;
use App\Models\Sympathisant;
use App\Models\User;
use App\Services\CasquettesService;
use App\Services\ConservationService;
use App\Services\SympathisantService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use UnitEnum;

/**
 * Le registre des sympathisants. Un Jewrine voit sa cellule ; la Jewrine des Sokhnas, les Sokhnas de sa cellule ;
 * le bureau et Suivi et Évaluation voient tout. Le Jewrine finance enregistre leurs participations.
 */
class SympathisantResource extends Resource
{
    protected static ?string $model = Sympathisant::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static string|UnitEnum|null $navigationGroup = 'Vie de la Daara';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Sympathisants';

    protected static ?string $modelLabel = 'sympathisant';

    protected static ?string $pluralModelLabel = 'sympathisants';

    private static function utilisateur(): ?User
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User ? $utilisateur : null;
    }

    public static function canViewAny(): bool
    {
        return ($utilisateur = self::utilisateur()) !== null && app(CasquettesService::class)->peutQuelquePart($utilisateur, 'sympathisants.voir');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        $utilisateur = self::utilisateur();

        return $utilisateur !== null && $record instanceof Sympathisant
            && app(CasquettesService::class)->peut($utilisateur, 'sympathisants.gerer', $record->cellule_id);
    }

    /** Rien ne se supprime : un sympathisant est retiré du registre par anonymisation. */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /** @return Builder<Sympathisant> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Sympathisant> $query */
        $query = parent::getEloquentQuery();
        $query->auRegistre();
        $utilisateur = self::utilisateur();

        if ($utilisateur !== null && ! $utilisateur->can('sympathisants.voir')) {
            $query->whereIn('cellule_id', app(CasquettesService::class)->cellulesDirigees($utilisateur));

            if (! Cellule::query()->dirigeesPar($utilisateur)->exists()) {
                $query->where('sexe', Sexe::Femme->value);
            }
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('prenom')->label('Prénom')->required()->maxLength(80),
            TextInput::make('nom')->label('Nom')->required()->maxLength(80),
            TextInput::make('telephone')->label('Téléphone')->tel()->maxLength(20)
                ->helperText('Seulement s\'il accepte d\'être recontacté.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['cellule', 'invitePar', 'membre'])->withCount('visites'))
            ->columns([
                TextColumn::make('prenom')->label('Prénom')->searchable(),
                TextColumn::make('nom')->label('Nom')->searchable(),
                TextColumn::make('cellule.nom')->label('Cellule'),
                TextColumn::make('sexe')->label('')->formatStateUsing(fn (mixed $state): string => $state === Sexe::Femme ? 'Sokhna' : 'Goor Yalla'),
                TextColumn::make('telephone')->label('Téléphone')->placeholder('—'),
                TextColumn::make('visites_count')->label('Visites')->sortable(),
                TextColumn::make('derniere_visite_le')->label('Dernière venue')->date('d/m/Y')->placeholder('—')->sortable(),
                TextColumn::make('situation')->label('Situation')->badge()
                    ->state(fn (Sympathisant $record): string => match (true) {
                        $record->membre_id !== null => 'Devenu membre',
                        $record->aInviter() => 'À inviter',
                        default => 'Sympathisant',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Devenu membre' => 'success',
                        'À inviter' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('cellule_id')->label('Cellule')->options(fn (): array => Cellule::query()->orderBy('nom')->pluck('nom', 'id')->all()),
                SelectFilter::make('sexe')->label('Goor Yalla / Sokhnas')->options(['H' => 'Goor Yalla', 'F' => 'Sokhnas']),
                Filter::make('a_inviter')
                    ->label('À inviter (3 visites en 3 mois)')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNull('membre_id')
                        ->whereRaw('(select count(*) from visites_sympathisants v where v.sympathisant_id = sympathisants.id and v.created_at >= ?) >= ?', [now()->subMonths(3), Sympathisant::VISITES_POUR_INVITER])),
            ])
            ->recordActions([
                Action::make('inviter')
                    ->label('Inviter à adhérer')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->visible(fn (Sympathisant $record): bool => app(SympathisantService::class)->lienInvitation($record) !== null)
                    ->url(fn (Sympathisant $record): ?string => app(SympathisantService::class)->lienInvitation($record))
                    ->openUrlInNewTab(),
                self::participation(),
                EditAction::make(),
                Action::make('retirer')
                    ->label('Retirer du registre')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->visible(fn (Sympathisant $record): bool => self::canEdit($record))
                    ->requiresConfirmation()
                    ->modalDescription('Son nom et son téléphone seront effacés. Ses visites et ses participations, avec leurs reçus, restent comptées sans le nommer.')
                    ->action(function (Sympathisant $record): void {
                        if (! self::canEdit($record)) {
                            return;
                        }

                        app(ConservationService::class)->anonymiserSympathisant($record);
                        Notification::make()->title('Retiré du registre.')->success()->send();
                    }),
            ])
            ->defaultSort('derniere_visite_le', 'desc');
    }

    private static function participation(): Action
    {
        return Action::make('participation')
            ->label('Participation')
            ->icon('heroicon-o-banknotes')
            ->color('gray')
            ->visible(fn (): bool => self::utilisateur()?->can('sympathisants.participations') ?? false)
            ->schema([
                Select::make('collecte_id')
                    ->label('Programme')
                    ->options(fn (): array => Collecte::query()->where('statut', StatutCollecte::Ouverte->value)->orderBy('titre')->pluck('titre', 'id')->all())
                    ->required(),
                TextInput::make('montant')->label('Montant reçu')->numeric()->minValue(1)->required()->suffix('FCFA'),
                Radio::make('moyen')->label('Moyen')->options([MoyenPaiement::Especes->value => 'Espèces', MoyenPaiement::Wave->value => 'Wave'])->default(MoyenPaiement::Especes->value)->required(),
            ])
            ->action(function (Sympathisant $record, array $data, Component $livewire): void {
                $auteur = self::utilisateur();
                abort_unless($auteur !== null, 403);

                try {
                    $participation = app(SympathisantService::class)->participer(
                        Collecte::query()->findOrFail((int) $data['collecte_id']),
                        $record,
                        (int) $data['montant'],
                        MoyenPaiement::from((string) $data['moyen']),
                        $auteur,
                    );
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Participation enregistrée : reçu '.$participation->numero_recu)->success()->send();
                $livewire->redirect(route('sympathisants.recu', $participation));
            });
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageSympathisants::route('/')];
    }
}
