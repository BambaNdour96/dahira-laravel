<?php

namespace App\Filament\Resources\Rapports\Pages;

use App\Filament\Resources\Rapports\RapportResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRapport extends EditRecord
{
    protected static string $resource = RapportResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [ViewAction::make()->label('Lire'), DeleteAction::make()];
    }
}
