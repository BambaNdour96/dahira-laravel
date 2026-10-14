<?php

namespace App\Filament\Resources\Instances\Widgets;

use App\Models\CommissionComite;
use App\Models\Instance;
use App\Services\CollecteService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Le budget de chaque commission : retenu, dépensé, reste ; orange au-delà de 90 %, rouge en dépassement. */
class BudgetCommissions extends TableWidget
{
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Budget des commissions')
            ->query(fn (): Builder => CommissionComite::query()
                ->where('instance_id', $this->record instanceof Instance ? $this->record->id : 0)
                ->whereNull('archive_le')
                ->orderBy('ordre'))
            ->columns([
                TextColumn::make('nom')->label('Commission')->weight('bold'),
                TextColumn::make('budget')->label('Budget retenu')
                    ->state(fn (CommissionComite $record): string => CollecteService::fcfa($record->budget())),
                TextColumn::make('depense')->label('Dépensé')
                    ->state(fn (CommissionComite $record): string => CollecteService::fcfa($record->depense())),
                TextColumn::make('reste')->label('Reste')
                    ->state(fn (CommissionComite $record): string => CollecteService::fcfa($record->budget() - $record->depense())),
                TextColumn::make('taux')->label('Consommé')->badge()
                    ->state(fn (CommissionComite $record): string => ($record->budget() > 0 ? (int) round($record->depense() / $record->budget() * 100) : 0).' %')
                    ->color(fn (string $state): string => (int) $state > 100 ? 'danger' : ((int) $state >= 90 ? 'warning' : 'success')),
            ])
            ->paginated(false);
    }
}
