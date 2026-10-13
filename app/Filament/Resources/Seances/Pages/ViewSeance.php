<?php

namespace App\Filament\Resources\Seances\Pages;

use App\Filament\Resources\Seances\SeanceResource;
use App\Filament\Resources\Seances\Widgets\BilanSeance;
use App\Filament\Resources\Seances\Widgets\ListePresence;
use App\Models\Evenement;
use App\Support\Confidentiel;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Component;

/** Une séance : le bilan (frères, sœurs, excusés, absents) et la liste de présence. */
class ViewSeance extends ViewRecord
{
    protected static string $resource = SeanceResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('pointer')
                ->label('Ouvrir le pointage')
                ->icon('heroicon-o-clipboard-document-check')
                ->url(fn (Evenement $record): string => route('pointage.show', $record)),
            Action::make('exporter')
                ->label('Excel')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Exporter la liste de présence')
                ->modalDescription(Confidentiel::AVERTISSEMENT)
                ->modalSubmitActionLabel('Télécharger')
                ->action(fn (Evenement $record, Component $livewire) => $livewire->redirect(route('evenements.presences.export', $record))),
            EditAction::make(),
        ];
    }

    /** @return array<int, class-string> */
    protected function getHeaderWidgets(): array
    {
        return [BilanSeance::class];
    }

    /** @return array<int, class-string> */
    protected function getFooterWidgets(): array
    {
        return [ListePresence::class];
    }
}
