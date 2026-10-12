<?php

namespace App\Filament\Resources\Projets\Pages;

use App\Filament\Resources\Projets\ProjetResource;
use App\Filament\Resources\Projets\Widgets\EtapesDuProjet;
use App\Filament\Resources\Projets\Widgets\IndicateursDuProjet;
use App\Filament\Resources\Projets\Widgets\JournalDuProjet;
use App\Filament\Resources\Projets\Widgets\ProjetChiffres;
use App\Models\Projet;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** Le tableau de bord d'un projet : chiffres et feu tricolore, étapes, indicateurs, journal. */
class ViewProjet extends ViewRecord
{
    protected static string $resource = ProjetResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('rapport')
                ->label('Rapport (PDF)')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn (Projet $record): string => route('projets.rapport', $record)),
            EditAction::make()->label('Modifier ou évaluer'),
        ];
    }

    /** @return array<int, class-string> */
    protected function getHeaderWidgets(): array
    {
        return [ProjetChiffres::class];
    }

    /** @return array<int, class-string> */
    protected function getFooterWidgets(): array
    {
        return [EtapesDuProjet::class, IndicateursDuProjet::class, JournalDuProjet::class];
    }
}
