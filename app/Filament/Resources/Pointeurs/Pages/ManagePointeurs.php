<?php

namespace App\Filament\Resources\Pointeurs\Pages;

use App\Filament\Resources\Pointeurs\PointeurResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePointeurs extends ManageRecords
{
    protected static string $resource = PointeurResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Désigner un pointeur')];
    }
}
