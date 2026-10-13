<?php

namespace App\Filament\Resources\Instances\Widgets;

use App\Models\ActionComite;
use App\Models\CommissionComite;
use App\Models\Instance;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** L'avancement de chaque commission : son chef, ses membres, ses actions faites et en retard. */
class AvancementCommissions extends TableWidget
{
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Commissions')
            ->query(fn (): Builder => CommissionComite::query()
                ->where('instance_id', $this->record instanceof Instance ? $this->record->id : 0)
                ->with(['chef', 'actions'])
                ->withCount('membres')
                ->orderBy('ordre'))
            ->columns([
                TextColumn::make('nom')->label('Commission')->weight('bold'),
                TextColumn::make('chef')->label('Chef')
                    ->state(fn (CommissionComite $record): string => $record->chef ? $record->chef->prenom.' '.$record->chef->nom : '—'),
                TextColumn::make('membres_count')->label('Membres'),
                TextColumn::make('faites')->label('Actions faites')
                    ->state(fn (CommissionComite $record): string => $record->actions->where('statut', 'faite')->count().' / '.$record->actions->count()),
                TextColumn::make('retard')->label('En retard')->badge()
                    ->state(fn (CommissionComite $record): int => $record->actions->filter(fn (ActionComite $action): bool => $action->estEnRetard())->count())
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success'),
            ])
            ->paginated(false);
    }
}
