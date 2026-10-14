<?php

namespace App\Filament\Resources\Membres\Widgets;

use App\Models\Membre;
use App\Models\Nomination;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Les casquettes d'un membre : ses nominations en cours, puis son historique. En lecture : on nomme dans « Nominations ». */
class CasquettesDuMembre extends TableWidget
{
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $userId = $this->record instanceof Membre ? $this->record->user_id : null;

        return $table
            ->heading('Casquettes')
            ->description('Postes occupés, en cours et passés. Les nominations se font dans Paramètres → Nominations.')
            ->query(fn (): Builder => Nomination::query()
                ->withoutGlobalScope(Nomination::EN_COURS)
                ->where('user_id', $userId ?? 0)
                ->with(['poste', 'cellule', 'designePar'])
                ->orderByRaw('fin_le is not null')
                ->orderByDesc('debut_le'))
            ->columns([
                TextColumn::make('poste')->label('Poste')->state(fn (Nomination $record): string => $record->libelle())->weight('bold'),
                TextColumn::make('debut_le')->label('Depuis le')->date('d/m/Y'),
                TextColumn::make('designePar.name')->label('Désigné par')->placeholder('Reprise des données'),
                TextColumn::make('fin_le')->label('Fin le')->date('d/m/Y')->placeholder('En cours'),
                TextColumn::make('motif_fin')->label('Motif de fin')->placeholder('—'),
            ])
            ->emptyStateHeading('Aucune casquette')
            ->paginated(false);
    }
}
