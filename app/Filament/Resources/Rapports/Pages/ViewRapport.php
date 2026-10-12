<?php

namespace App\Filament\Resources\Rapports\Pages;

use App\Filament\Resources\Rapports\RapportResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRapport extends ViewRecord
{
    protected static string $resource = RapportResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [RapportResource::publier(), RapportResource::pdf(), EditAction::make()];
    }
}
