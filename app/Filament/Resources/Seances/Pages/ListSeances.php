<?php

namespace App\Filament\Resources\Seances\Pages;

use App\Filament\Resources\Seances\SeanceResource;
use App\Filament\Resources\Seances\Widgets\AssiduiteDesMembres;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSeances extends ListRecords
{
    protected static string $resource = SeanceResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nouvelle séance')];
    }

    /** @return array<int, class-string> */
    protected function getFooterWidgets(): array
    {
        return [AssiduiteDesMembres::class];
    }
}
