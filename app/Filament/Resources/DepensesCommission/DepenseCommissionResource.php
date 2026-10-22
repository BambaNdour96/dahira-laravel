<?php

namespace App\Filament\Resources\DepensesCommission;

use App\Filament\Resources\DepensesCommission\Pages\ListDepensesCommission;
use App\Models\DepenseCommission;
use App\Models\Instance;
use App\Models\User;
use App\Services\CollecteService;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/** Les anciennes dépenses des commissions, déclarées après coup (avant les avances et remboursements) : en consultation seulement. */
class DepenseCommissionResource extends Resource
{
    protected static ?string $model = DepenseCommission::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static string|UnitEnum|null $navigationGroup = 'Comités et réunions';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Anciennes dépenses des commissions';

    protected static ?string $modelLabel = 'dépense';

    protected static ?string $pluralModelLabel = 'dépenses';

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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['commission.instance', 'declarant']))
            ->columns([
                TextColumn::make('date_depense')->label('Date')->date('d/m/Y')->sortable(),
                TextColumn::make('commission.nom')->label('Commission')->weight('bold'),
                TextColumn::make('objet')->label('Objet')->searchable()->wrap(),
                TextColumn::make('fournisseur')->label('Fournisseur')->placeholder('—'),
                TextColumn::make('montant')->label('Montant')->sortable()
                    ->formatStateUsing(fn (mixed $state): string => CollecteService::fcfa((int) $state)),
                TextColumn::make('declarant.name')->label('Déclarée par')->placeholder('—'),
                TextColumn::make('justificatif')->label('Justificatif')
                    ->state(fn (DepenseCommission $record): string => $record->justificatif_path !== null ? 'Voir' : '—')
                    ->url(fn (DepenseCommission $record): ?string => $record->justificatif_path !== null ? route('justificatifs.show', $record) : null)
                    ->openUrlInNewTab(),
                TextColumn::make('statut')->label('État')->badge()
                    ->formatStateUsing(fn (string $state): string => DepenseCommission::STATUTS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'validee' => 'success',
                        'refusee' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('instance')->label('Comité')
                    ->options(fn (): array => Instance::query()->where('type', '!=', Instance::COMITE_DIRECTEUR)->pluck('nom', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('commission', fn (Builder $commission) => $commission->where('instance_id', $data['value']))
                        : $query),
                SelectFilter::make('statut')->label('État')->options(DepenseCommission::STATUTS),
            ])
            ->defaultSort('date_depense', 'desc');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListDepensesCommission::route('/')];
    }
}
