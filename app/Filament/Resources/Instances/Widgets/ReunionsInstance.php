<?php

namespace App\Filament\Resources\Instances\Widgets;

use App\Filament\Resources\Reunions\ReunionResource;
use App\Models\Instance;
use App\Models\Reunion;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Les réunions d'un comité, des plus récentes aux plus anciennes. */
class ReunionsInstance extends TableWidget
{
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Réunions')
            ->query(fn (): Builder => Reunion::query()
                ->where('instance_id', $this->record instanceof Instance ? $this->record->id : 0)
                ->withCount(['decisions', 'actions']))
            ->columns([
                TextColumn::make('debut')->label('Date')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('titre')->label('Réunion')->weight('bold'),
                TextColumn::make('lieu')->label('Lieu'),
                TextColumn::make('decisions_count')->label('Décisions'),
                TextColumn::make('actions_count')->label('Actions'),
                TextColumn::make('statut')->label('État')->badge()
                    ->formatStateUsing(fn (string $state): string => ReunionResource::ETATS[$state] ?? $state)
                    ->color(fn (string $state): string => $state === 'publiee' ? 'success' : ($state === 'convoquee' ? 'info' : 'gray')),
            ])
            ->recordActions([
                Action::make('ouvrir')->label('Ouvrir')->url(fn (Reunion $record): string => ReunionResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('debut', 'desc');
    }
}
