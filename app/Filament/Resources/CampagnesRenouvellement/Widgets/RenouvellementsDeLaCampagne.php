<?php

namespace App\Filament\Resources\CampagnesRenouvellement\Widgets;

use App\Filament\Resources\CampagnesRenouvellement\CampagneRenouvellementResource;
use App\Models\CampagneRenouvellement;
use App\Models\Renouvellement;
use App\Services\RenouvellementService;
use DomainException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Chaque membre concerné par la campagne ; le Jewrine revalide ceux dont la carte a changé. */
class RenouvellementsDeLaCampagne extends TableWidget
{
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $cellules = CampagneRenouvellementResource::cellulesSuivies();
        $campagneId = $this->record instanceof CampagneRenouvellement ? $this->record->id : 0;

        return $table
            ->heading('Membres')
            ->query(fn (): Builder => Renouvellement::query()
                ->where('campagne_id', $campagneId)
                ->with(['membre.cellule', 'paiement'])
                ->when($cellules !== null, fn (Builder $query) => $query->whereHas('membre', fn (Builder $membre) => $membre->whereIn('cellule_id', $cellules ?? []))))
            ->columns([
                TextColumn::make('membre.nom_complet')->label('Membre'),
                TextColumn::make('membre.matricule')->label('Matricule')->placeholder('—'),
                TextColumn::make('membre.cellule.nom')->label('Cellule'),
                TextColumn::make('statut')->label('État')->badge()
                    ->formatStateUsing(fn (string $state): string => Renouvellement::LIBELLES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Renouvellement::RENOUVELE, Renouvellement::D_OFFICE => 'success',
                        Renouvellement::A_REVALIDER => 'warning',
                        Renouvellement::NON_RENOUVELE => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('renouvele_le')->label('Renouvelé le')->date('d/m/Y')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('statut')->label('État')->options(Renouvellement::LIBELLES),
            ])
            ->recordActions([
                Action::make('revalider')
                    ->label('Revalider')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Renouvellement $record): bool => $record->statut === Renouvellement::A_REVALIDER)
                    ->requiresConfirmation()
                    ->modalDescription('Vérifiez d\'abord la fiche du membre (prénom, nom, sexe, date de naissance, photo). Sa nouvelle carte sera émise aussitôt.')
                    ->action(function (Renouvellement $record): void {
                        $auteur = CampagneRenouvellementResource::utilisateur();
                        abort_unless($auteur !== null, 403);

                        try {
                            app(RenouvellementService::class)->revalider($record, $auteur);
                        } catch (DomainException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Renouvellement revalidé : la nouvelle carte est émise.')->success()->send();
                    }),
                Action::make('fiche')
                    ->label('Fiche')
                    ->icon('heroicon-o-user')
                    ->color('gray')
                    ->url(fn (Renouvellement $record): string => route('filament.admin.resources.membres.edit', $record->membre)),
            ])
            ->defaultSort('statut');
    }
}
