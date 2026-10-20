<?php

namespace App\Filament\Resources\Reaffectations\Pages;

use App\Filament\Resources\Reaffectations\ReaffectationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListReaffectations extends ListRecords
{
    protected static string $resource = ReaffectationResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [ReaffectationResource::decider()];
    }
}
