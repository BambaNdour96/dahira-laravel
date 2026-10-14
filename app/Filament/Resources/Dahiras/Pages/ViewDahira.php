<?php

namespace App\Filament\Resources\Dahiras\Pages;

use App\Filament\Resources\Dahiras\DahiraResource;
use App\Filament\Resources\Dahiras\Widgets\BilanDahira;
use App\Filament\Resources\Dahiras\Widgets\ListePresence;
use App\Models\Evenement;
use App\Support\Confidentiel;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Component;

/** Une dahira : le bilan (Goor Yalla, Sokhnas, excusés, absents) et la liste de présence. */
class ViewDahira extends ViewRecord
{
    protected static string $resource = DahiraResource::class;

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
        return [BilanDahira::class];
    }

    /** @return array<int, class-string> */
    protected function getFooterWidgets(): array
    {
        return [ListePresence::class];
    }
}
