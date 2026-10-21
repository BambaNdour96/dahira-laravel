<?php

namespace App\Filament\Resources\Instances\RelationManagers;

use App\Models\ActionComite;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Onglet « Tâches » : les actions du comité et les tâches de ses commissions, avec leur avancement. */
class TachesRelationManager extends OngletComite
{
    protected static string $relationship = 'actions';

    protected static ?string $title = 'Tâches';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['commission', 'responsable']))
            ->columns([
                TextColumn::make('description')->label('Tâche')->limit(60)->searchable(),
                TextColumn::make('commission.nom')->label('Commission')->placeholder('Le comité'),
                TextColumn::make('responsable.nom')->label('Responsable')
                    ->formatStateUsing(fn (ActionComite $record): string => $record->responsable !== null ? $record->responsable->prenom.' '.$record->responsable->nom : '—'),
                TextColumn::make('echeance')->label('Pour le')->date('d/m/Y')->placeholder('—')->sortable(),
                TextColumn::make('statut')->label('État')->badge()
                    ->state(fn (ActionComite $record): string => $record->estEnRetard() ? 'En retard' : ActionComite::STATUTS[$record->statut])
                    ->color(fn (ActionComite $record): string => match (true) {
                        $record->estEnRetard() => 'danger',
                        $record->statut === 'faite' => 'success',
                        default => 'gray',
                    }),
            ])
            ->filters([SelectFilter::make('statut')->label('État')->options(ActionComite::STATUTS)])
            ->defaultSort('echeance');
    }
}
