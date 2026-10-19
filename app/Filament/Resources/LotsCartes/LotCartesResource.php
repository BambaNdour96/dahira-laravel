<?php

namespace App\Filament\Resources\LotsCartes;

use App\Filament\Resources\LotsCartes\Pages\ListLotsCartes;
use App\Models\Cellule;
use App\Models\LotCartes;
use App\Models\User;
use App\Services\CartePhysiqueService;
use BackedEnum;
use Closure;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/** Les lots de cartes en plastique pour l'imprimeur : préparés par le super administrateur et le Secrétaire général. */
class LotCartesResource extends Resource
{
    protected static ?string $model = LotCartes::class;

    protected static ?string $slug = 'lots-cartes';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-printer';

    protected static string|UnitEnum|null $navigationGroup = 'Registre';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'lot de cartes';

    protected static ?string $pluralModelLabel = 'lots de cartes';

    protected static ?string $navigationLabel = 'Cartes à imprimer';

    public static function utilisateur(): ?User
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User ? $utilisateur : null;
    }

    public static function canViewAny(): bool
    {
        return self::utilisateur()?->can('cartes.imprimer') === true;
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
        $attente = self::canViewAny() ? app(CartePhysiqueService::class)->enAttente() : 0;

        return $attente > 0 ? (string) $attente : null;
    }

    public static function preparer(): Action
    {
        return Action::make('preparer')
            ->label('Préparer un lot')
            ->icon('heroicon-o-rectangle-stack')
            ->visible(fn (): bool => self::canViewAny())
            ->modalDescription(fn (): string => app(CartePhysiqueService::class)->enAttente().' carte(s) payée(s) attendent l\'impression. Le lot prend la carte active de chaque membre.')
            ->schema([
                Select::make('cellule_id')->label('Cellule (facultatif : toutes)')
                    ->options(fn (): array => Cellule::query()->enActivite()->orderBy('nom')->pluck('nom', 'id')->all()),
            ])
            ->action(function (array $data): void {
                self::executer(function (CartePhysiqueService $service, User $auteur) use ($data): void {
                    $lot = $service->preparerLot(filled($data['cellule_id'] ?? null) ? Cellule::query()->whereKey($data['cellule_id'])->first() : null, $auteur);
                    Notification::make()->title('Lot '.$lot->numero.' préparé : '.$lot->cartes()->count().' carte(s).')->success()->send();
                });
            });
    }

    /** @param  Closure(CartePhysiqueService, User): void  $operation */
    private static function executer(Closure $operation): void
    {
        $auteur = self::utilisateur();
        abort_unless($auteur !== null, 403);

        try {
            $operation(app(CartePhysiqueService::class), $auteur);
        } catch (DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('cellule')->withCount('cartes'))
            ->columns([
                TextColumn::make('numero')->label('Lot')->weight('bold')->searchable(),
                TextColumn::make('cellule.nom')->label('Cellule')->placeholder('Toutes'),
                TextColumn::make('cartes_count')->label('Cartes'),
                TextColumn::make('created_at')->label('Préparé le')->date('d/m/Y'),
                TextColumn::make('statut')->label('État')->badge()
                    ->formatStateUsing(fn (string $state): string => LotCartes::LIBELLES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        LotCartes::RECU => 'success',
                        LotCartes::ENVOYE => 'info',
                        default => 'gray',
                    }),
            ])
            ->recordActions([
                Action::make('pdf')->label('PDF')->icon('heroicon-o-document-arrow-down')->color('gray')
                    ->url(fn (LotCartes $record): string => route('lots-cartes.fichier', [$record, 'pdf'])),
                Action::make('csv')->label('CSV')->icon('heroicon-o-table-cells')->color('gray')
                    ->url(fn (LotCartes $record): string => route('lots-cartes.fichier', [$record, 'csv'])),
                Action::make('envoyer')->label('Envoyé à l\'imprimeur')->icon('heroicon-o-paper-airplane')
                    ->visible(fn (LotCartes $record): bool => $record->statut === LotCartes::PREPARE)
                    ->requiresConfirmation()
                    ->action(fn (LotCartes $record) => self::executer(function (CartePhysiqueService $service, User $auteur) use ($record): void {
                        $service->envoyer($record, $auteur);
                        Notification::make()->title('Lot envoyé à l\'imprimeur.')->success()->send();
                    })),
                Action::make('recevoir')->label('Reçu')->icon('heroicon-o-inbox-arrow-down')->color('success')
                    ->visible(fn (LotCartes $record): bool => $record->statut === LotCartes::ENVOYE)
                    ->requiresConfirmation()
                    ->modalDescription('Chaque membre du lot est prévenu que sa carte est à retirer auprès de son Jewrine de cellule.')
                    ->action(fn (LotCartes $record) => self::executer(function (CartePhysiqueService $service, User $auteur) use ($record): void {
                        $service->recevoir($record, $auteur);
                        Notification::make()->title('Lot reçu : les membres sont prévenus.')->success()->send();
                    })),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListLotsCartes::route('/')];
    }
}
