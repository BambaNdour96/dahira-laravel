<?php

namespace App\Filament\Resources\Membres\Pages;

use App\Filament\Resources\Membres\Actions\CarteActions;
use App\Filament\Resources\Membres\Actions\ChangerStatutAction;
use App\Filament\Resources\Membres\MembreResource;
use App\Filament\Resources\Membres\Widgets\CasquettesDuMembre;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditMembre extends EditRecord
{
    protected static string $resource = MembreResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [...ChangerStatutAction::toutes(), ...CarteActions::toutes()];
    }

    /** @return array<int, class-string> */
    protected function getFooterWidgets(): array
    {
        return [CasquettesDuMembre::class];
    }
}
