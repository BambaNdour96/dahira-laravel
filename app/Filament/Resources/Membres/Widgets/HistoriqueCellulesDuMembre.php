<?php

namespace App\Filament\Resources\Membres\Widgets;

use App\Models\HistoriqueCellule;
use App\Models\Membre;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Les cellules successives d'un membre, la plus récente d'abord. En lecture : on change de cellule par une demande validée. */
class HistoriqueCellulesDuMembre extends TableWidget
{
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $membreId = $this->record instanceof Membre ? $this->record->id : 0;

        return $table
            ->heading('Historique des cellules')
            ->description('Un changement de cellule se demande sur la fiche (« Demander un changement de cellule ») et se valide par le super administrateur.')
            ->query(fn (): Builder => HistoriqueCellule::query()
                ->where('membre_id', $membreId)
                ->with(['cellule', 'changement.decideur', 'changement.demandeur'])
                ->orderByRaw('fin_le is not null')
                ->orderByDesc('debut_le')
                ->orderByDesc('id'))
            ->columns([
                TextColumn::make('cellule.nom')->label('Cellule')->weight('bold'),
                TextColumn::make('debut_le')->label('Du')->date('d/m/Y'),
                TextColumn::make('fin_le')->label('Au')->date('d/m/Y')->placeholder('En cours'),
                TextColumn::make('origine')->label('Origine')
                    ->state(fn (HistoriqueCellule $record): string => $record->changement !== null
                        ? 'Changement demandé par '.$record->changement->demandeur->name.', validé par '.($record->changement->decideur->name ?? '—')
                        : 'Inscription ou rattachement à la famille'),
                TextColumn::make('changement.motif')->label('Motif')->placeholder('—'),
            ])
            ->emptyStateHeading('Aucun historique')
            ->paginated(false);
    }
}
