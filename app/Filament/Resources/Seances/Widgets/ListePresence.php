<?php

namespace App\Filament\Resources\Seances\Widgets;

use App\Enums\Sexe;
use App\Enums\StatutMembre;
use App\Models\Evenement;
use App\Models\Membre;
use App\Models\Presence;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** La liste de présence : chaque membre de la cellule, présent, excusé ou absent. */
class ListePresence extends TableWidget
{
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    private function seance(): Evenement
    {
        abort_unless($this->record instanceof Evenement, 404);

        return $this->record;
    }

    public function table(Table $table): Table
    {
        $presences = fn (): Collection => Presence::query()
            ->where('evenement_id', $this->seance()->id)
            ->with('controleur')
            ->get()
            ->keyBy('membre_id');

        return $table
            ->heading('Liste de présence')
            ->query(fn (): Builder => Membre::query()
                ->where('statut', StatutMembre::Valide->value)
                ->where('cellule_id', $this->seance()->cellule_id)
                ->orderBy('nom'))
            ->columns([
                TextColumn::make('prenom')->label('Prénom')->searchable(),
                TextColumn::make('nom')->label('Nom')->searchable(),
                TextColumn::make('sexe')->label('')->formatStateUsing(fn (mixed $state): string => $state === Sexe::Femme ? 'Sœur' : 'Frère'),
                TextColumn::make('etat')->label('Présence')->badge()
                    ->state(function (Membre $record) use ($presences): string {
                        $presence = $presences()->get($record->id);

                        return match ($presence?->statut) {
                            'present' => 'Présent',
                            'excuse' => 'Excusé',
                            default => 'Absent',
                        };
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Présent' => 'success',
                        'Excusé' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('detail')->label('Détail')->placeholder('—')
                    ->state(function (Membre $record) use ($presences): ?string {
                        $presence = $presences()->get($record->id);

                        if ($presence === null) {
                            return null;
                        }

                        return $presence->statut === 'excuse'
                            ? 'Motif : '.$presence->motif
                            : $presence->libelleMode().' par '.($presence->controleur->name ?? '—').' à '.$presence->created_at->format('H:i');
                    }),
            ])
            ->filters([
                SelectFilter::make('sexe')->label('Frères / sœurs')->options(['H' => 'Frères', 'F' => 'Sœurs']),
            ]);
    }
}
