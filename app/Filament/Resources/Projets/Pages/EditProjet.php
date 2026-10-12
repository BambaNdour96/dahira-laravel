<?php

namespace App\Filament\Resources\Projets\Pages;

use App\Filament\Resources\Projets\ProjetResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProjet extends EditRecord
{
    protected static string $resource = ProjetResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [ViewAction::make()->label('Tableau de bord'), DeleteAction::make()];
    }
}
