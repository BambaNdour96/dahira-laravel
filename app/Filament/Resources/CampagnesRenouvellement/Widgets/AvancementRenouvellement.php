<?php

namespace App\Filament\Resources\CampagnesRenouvellement\Widgets;

use App\Filament\Resources\CampagnesRenouvellement\CampagneRenouvellementResource;
use App\Models\CampagneRenouvellement;
use App\Models\Cellule;
use App\Models\Renouvellement;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** L'avancement de la campagne, cellule par cellule (le Jewrine ne voit que la sienne). */
class AvancementRenouvellement extends TableWidget
{
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    private function compter(Cellule $cellule, string ...$statuts): int
    {
        return Renouvellement::query()
            ->where('campagne_id', $this->record instanceof CampagneRenouvellement ? $this->record->id : 0)
            ->whereIn('statut', $statuts)
            ->whereHas('membre', fn (Builder $membre) => $membre->where('cellule_id', $cellule->id))
            ->count();
    }

    public function table(Table $table): Table
    {
        $cellules = CampagneRenouvellementResource::cellulesSuivies();

        return $table
            ->heading('Avancement par cellule')
            ->query(fn (): Builder => Cellule::query()->when($cellules !== null, fn (Builder $query) => $query->whereKey($cellules ?? []))->orderBy('nom'))
            ->columns([
                TextColumn::make('nom')->label('Cellule')->weight('bold'),
                TextColumn::make('renouveles')->label('Renouvelés')->state(fn (Cellule $record): int => $this->compter($record, Renouvellement::RENOUVELE, Renouvellement::D_OFFICE)),
                TextColumn::make('a_revalider')->label('À revalider')->state(fn (Cellule $record): int => $this->compter($record, Renouvellement::A_REVALIDER)),
                TextColumn::make('a_faire')->label('À faire')->state(fn (Cellule $record): int => $this->compter($record, Renouvellement::A_FAIRE)),
                TextColumn::make('non_renouveles')->label('Non renouvelés')->state(fn (Cellule $record): int => $this->compter($record, Renouvellement::NON_RENOUVELE)),
            ])
            ->paginated(false);
    }
}
