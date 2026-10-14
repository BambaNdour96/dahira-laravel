<?php

namespace App\Filament\Resources\Instances\Widgets;

use App\Filament\Actions\ConservationActions;
use App\Models\ActionComite;
use App\Models\CommissionComite;
use App\Models\Instance;
use App\Models\User;
use App\Services\ComiteService;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** L'avancement de chaque commission : son chef, ses membres, ses actions faites et en retard. */
class AvancementCommissions extends TableWidget
{
    public ?Model $record = null;

    public bool $voirArchives = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Commissions')
            ->query(fn (): Builder => CommissionComite::query()
                ->where('instance_id', $this->record instanceof Instance ? $this->record->id : 0)
                ->when(! $this->voirArchives, fn (Builder $query) => $query->whereNull('archive_le'))
                ->with(['chef', 'actions'])
                ->withCount('membres')
                ->orderBy('ordre'))
            ->columns([
                TextColumn::make('nom')->label('Commission')->weight('bold'),
                TextColumn::make('chef')->label('Chef')
                    ->state(fn (CommissionComite $record): string => $record->chef ? $record->chef->prenom.' '.$record->chef->nom : '—'),
                TextColumn::make('membres_count')->label('Membres'),
                TextColumn::make('faites')->label('Actions faites')
                    ->state(fn (CommissionComite $record): string => $record->actions->where('statut', 'faite')->count().' / '.$record->actions->where('statut', '!=', 'abandonnee')->count()),
                TextColumn::make('retard')->label('En retard')->badge()
                    ->state(fn (CommissionComite $record): int => $record->actions->filter(fn (ActionComite $action): bool => $action->estEnRetard())->count())
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success'),
            ])
            ->headerActions([
                Action::make('archives')
                    ->label(fn (): string => $this->voirArchives ? 'Masquer les archivées' : 'Voir les archivées')
                    ->color('gray')
                    ->link()
                    ->action(function (): void {
                        $this->voirArchives = ! $this->voirArchives;
                    }),
            ])
            ->recordActions([
                ConservationActions::archiver(fn (Model $record): bool => $this->peutGerer()),
                ConservationActions::desarchiver(fn (Model $record): bool => $this->peutGerer()),
            ])
            ->paginated(false);
    }

    private function peutGerer(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User && $this->record instanceof Instance && ComiteService::peutGerer($utilisateur, $this->record);
    }
}
