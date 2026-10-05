<?php

namespace App\Filament\Resources\Postes\Pages;

use App\Filament\Resources\Postes\PosteResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePostes extends ManageRecords
{
    protected static string $resource = PosteResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
