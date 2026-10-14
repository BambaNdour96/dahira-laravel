<?php

namespace App\Filament\Resources\Projets\Widgets;

use App\Filament\Actions\ConservationActions;
use App\Models\Jalon;
use App\Models\Projet;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Les étapes (jalons) d'un projet : date prévue, date réelle, état. */
class EtapesDuProjet extends TableWidget
{
    public ?Model $record = null;

    public bool $voirArchives = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Étapes du projet')
            ->query(fn (): Builder => Jalon::query()
                ->where('projet_id', $this->projet()->id)
                ->when(! $this->voirArchives, fn (Builder $query) => $query->whereNull('archive_le')))
            ->columns([
                TextColumn::make('titre')->label('Étape')->wrap(),
                TextColumn::make('echeance')->label('Prévue le')->date('d/m/Y')->sortable(),
                TextColumn::make('realise_le')->label('Réalisée le')->date('d/m/Y')->placeholder('—'),
                TextColumn::make('etat')->label('État')->badge()
                    ->state(fn (Jalon $record): string => $record->etat())
                    ->color(fn (string $state): string => match ($state) {
                        'Réalisée' => 'success',
                        'En retard' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                Action::make('archives')
                    ->label(fn (): string => $this->voirArchives ? 'Masquer les archivés' : 'Voir les archivés')
                    ->color('gray')
                    ->link()
                    ->action(function (): void {
                        $this->voirArchives = ! $this->voirArchives;
                    }),
                Action::make('ajouterEtape')
                    ->label('Ajouter une étape')
                    ->icon('heroicon-o-plus')
                    ->visible(fn (): bool => $this->peutGerer())
                    ->schema(self::formulaire())
                    ->action(function (array $data): void {
                        $this->projet()->jalons()->create($data);
                        Notification::make()->title('Étape ajoutée.')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('realiser')
                    ->label('Réalisée')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Jalon $record): bool => $record->realise_le === null && $this->peutGerer())
                    ->schema([
                        DatePicker::make('realise_le')->label('Réalisée le')->default(now())->required()->native(false),
                    ])
                    ->action(function (Jalon $record, array $data): void {
                        $record->update(['realise_le' => $data['realise_le']]);
                        Notification::make()->title('Étape marquée réalisée.')->success()->send();
                    }),
                EditAction::make()->schema(self::formulaire())->visible(fn (): bool => $this->peutGerer()),
                ConservationActions::archiver(fn (Model $record): bool => $this->peutGerer()),
                ConservationActions::desarchiver(fn (Model $record): bool => $this->peutGerer()),
            ])
            ->defaultSort('echeance');
    }

    /** @return array<int, Field> */
    private static function formulaire(): array
    {
        return [
            TextInput::make('titre')->label('Étape')->required()->maxLength(150),
            DatePicker::make('echeance')->label('Prévue le')->required()->native(false),
        ];
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
