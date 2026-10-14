<?php

namespace App\Filament\Resources\Dahiras\Pages;

use App\Filament\Resources\Dahiras\DahiraResource;
use App\Filament\Resources\Dahiras\Widgets\AssiduiteDesMembres;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDahiras extends ListRecords
{
    protected static string $resource = DahiraResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nouvelle dahira')];
    }

    /** @return array<int, class-string> */
    protected function getFooterWidgets(): array
    {
        return [AssiduiteDesMembres::class];
    }
}
