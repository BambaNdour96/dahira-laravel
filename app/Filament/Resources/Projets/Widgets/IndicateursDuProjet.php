<?php

namespace App\Filament\Resources\Projets\Widgets;

use App\Models\Indicateur;
use App\Models\Projet;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Ce que l'on mesure : la cible, la valeur atteinte et le pourcentage. */
class IndicateursDuProjet extends TableWidget
{
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $nombre = fn (float $valeur): string => rtrim(rtrim(number_format($valeur, 2, ',', ' '), '0'), ',');

        return $table
            ->heading('Indicateurs')
            ->query(fn (): Builder => Indicateur::query()->where('projet_id', $this->projet()->id))
            ->columns([
                TextColumn::make('libelle')->label('Indicateur')->wrap(),
                TextColumn::make('cible')->label('Cible')
                    ->formatStateUsing(fn (mixed $state, Indicateur $record): string => $nombre((float) $state).' '.$record->unite),
                TextColumn::make('valeur')->label('Atteint')
                    ->formatStateUsing(fn (mixed $state, Indicateur $record): string => $nombre((float) $state).' '.$record->unite),
                TextColumn::make('atteinte')->label('Atteinte')->badge()
                    ->state(fn (Indicateur $record): string => $record->atteinte().' %')
                    ->color(fn (Indicateur $record): string => match (true) {
                        $record->atteinte() >= 100 => 'success',
                        $record->atteinte() >= 50 => 'warning',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                Action::make('ajouterIndicateur')
                    ->label('Ajouter un indicateur')
                    ->icon('heroicon-o-plus')
                    ->visible(fn (): bool => $this->peutGerer())
                    ->schema([
                        TextInput::make('libelle')->label('Indicateur')->placeholder('Exemple : membres inscrits')->required()->maxLength(150),
                        TextInput::make('cible')->label('Cible')->numeric()->minValue(0)->required(),
                        TextInput::make('unite')->label('Unité')->placeholder('Exemple : membres, %, FCFA')->maxLength(30),
                    ])
                    ->action(function (array $data): void {
                        $this->projet()->indicateurs()->create($data);
                        Notification::make()->title('Indicateur ajouté.')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('mesurer')
                    ->label('Mettre à jour')
                    ->icon('heroicon-o-arrow-trending-up')
                    ->visible(fn (): bool => $this->peutGerer())
                    ->fillForm(fn (Indicateur $record): array => ['valeur' => $record->valeur])
                    ->schema([
                        TextInput::make('valeur')->label('Valeur atteinte')->numeric()->minValue(0)->required(),
                    ])
                    ->action(function (Indicateur $record, array $data): void {
                        $record->update(['valeur' => $data['valeur']]);
                        Notification::make()->title('Indicateur mis à jour.')->success()->send();
                    }),
                DeleteAction::make()->visible(fn (): bool => $this->peutGerer()),
            ]);
    }

    private function projet(): Projet
    {
        abort_unless($this->record instanceof Projet, 404);

        return $this->record;
    }

    private function peutGerer(): bool
    {
        return Gate::allows('update', $this->projet());
    }
}
