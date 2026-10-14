<?php

namespace App\Filament\Resources\BesoinsCommission;

use App\Filament\Resources\BesoinsCommission\Pages\ListBesoinsCommission;
use App\Models\BesoinCommission;
use App\Models\Instance;
use App\Models\User;
use App\Services\BudgetComiteService;
use App\Services\CollecteService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/** Les besoins exprimés par les commissions, que le président retient ou écarte. */
class BesoinCommissionResource extends Resource
{
    protected static ?string $model = BesoinCommission::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Comités et réunions';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Besoins des commissions';

    protected static ?string $modelLabel = 'besoin';

    protected static ?string $pluralModelLabel = 'besoins';

    public static function canViewAny(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User
            && $utilisateur->can('comites.budget_voir');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['commission.instance']))
            ->columns([
                TextColumn::make('commission.nom')->label('Commission')->weight('bold'),
                TextColumn::make('designation')->label('Besoin')->searchable()->wrap(),
                TextColumn::make('quantite')->label('Qté'),
                TextColumn::make('prix_unitaire')->label('Prix unitaire')
                    ->formatStateUsing(fn (mixed $state): string => CollecteService::fcfa((int) $state)),
                TextColumn::make('total')->label('Total')
                    ->state(fn (BesoinCommission $record): string => CollecteService::fcfa($record->montant())),
                TextColumn::make('statut')->label('État')->badge()
                    ->formatStateUsing(fn (string $state): string => BesoinCommission::STATUTS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'retenu' => 'success',
                        'ecarte' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('motif')->label('Motif')->placeholder('—')->wrap(),
            ])
            ->filters([
                SelectFilter::make('instance')->label('Comité')
                    ->options(fn (): array => Instance::query()->where('type', '!=', Instance::COMITE_DIRECTEUR)->pluck('nom', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('commission', fn (Builder $commission) => $commission->where('instance_id', $data['value']))
                        : $query),
                SelectFilter::make('statut')->label('État')->options(BesoinCommission::STATUTS),
            ])
            ->recordActions([
                Action::make('retenir')
                    ->label('Retenir')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (BesoinCommission $record): bool => $record->statut !== 'retenu'
                        && ($utilisateur = Auth::user()) instanceof User && BudgetComiteService::peutRetenir($utilisateur, $record->commission))
                    ->action(function (BesoinCommission $record): void {
                        self::traiter(fn (User $utilisateur) => app(BudgetComiteService::class)->retenir($record, $utilisateur), 'Besoin retenu : il entre dans le budget de la commission.');
                    }),
                Action::make('ecarter')
                    ->label('Écarter')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (BesoinCommission $record): bool => $record->statut !== 'ecarte'
                        && ($utilisateur = Auth::user()) instanceof User && BudgetComiteService::peutRetenir($utilisateur, $record->commission))
                    ->schema([TextInput::make('motif')->label('Motif')->required()->maxLength(255)])
                    ->action(function (BesoinCommission $record, array $data): void {
                        self::traiter(fn (User $utilisateur) => app(BudgetComiteService::class)->ecarter($record, (string) $data['motif'], $utilisateur), 'Besoin écarté.');
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function traiter(\Closure $operation, string $succes): void
    {
        $utilisateur = Auth::user();
        abort_unless($utilisateur instanceof User, 403);

        try {
            $operation($utilisateur);
        } catch (DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($succes)->success()->send();
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListBesoinsCommission::route('/')];
    }
}
