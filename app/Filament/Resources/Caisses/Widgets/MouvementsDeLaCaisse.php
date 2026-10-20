<?php

namespace App\Filament\Resources\Caisses\Widgets;

use App\Models\Caisse;
use App\Models\MouvementCaisse;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Les entrées et sorties d'une caisse, la plus récente d'abord. */
class MouvementsDeLaCaisse extends TableWidget
{
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $caisseId = $this->record instanceof Caisse ? $this->record->id : 0;

        return $table
            ->heading('Mouvements')
            ->query(fn (): Builder => MouvementCaisse::query()->with('auteur')->where('caisse_id', $caisseId))
            ->columns([
                TextColumn::make('date_operation')->label('Date')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('nature')->label('Nature')->formatStateUsing(fn (string $state): string => MouvementCaisse::NATURES[$state] ?? $state),
                TextColumn::make('libelle')->label('Libellé')->wrap(),
                TextColumn::make('entree')->label('Entrée')->alignEnd()
                    ->state(fn (MouvementCaisse $record): string => $record->sens === MouvementCaisse::ENTREE ? number_format($record->montant, 0, ',', ' ') : ''),
                TextColumn::make('sortie')->label('Sortie')->alignEnd()
                    ->state(fn (MouvementCaisse $record): string => $record->sens === MouvementCaisse::SORTIE ? number_format($record->montant, 0, ',', ' ') : ''),
                TextColumn::make('auteur.name')->label('Par')->placeholder('—'),
            ])
            ->filters([SelectFilter::make('nature')->label('Nature')->options(MouvementCaisse::NATURES)])
            ->defaultSort('date_operation', 'desc');
    }
}
