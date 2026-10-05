<?php

namespace App\Filament\Resources\Sections\Pages;

use App\Filament\Resources\Sections\SectionResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSections extends ManageRecords
{
    protected static string $resource = SectionResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
