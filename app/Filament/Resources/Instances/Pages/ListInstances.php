<?php

namespace App\Filament\Resources\Instances\Pages;

use App\Filament\Resources\Instances\InstanceResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInstances extends ListRecords
{
    protected static string $resource = InstanceResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nouveau comité (CDGMT…)')];
    }
}
