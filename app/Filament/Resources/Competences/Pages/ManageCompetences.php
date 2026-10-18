<?php

namespace App\Filament\Resources\Competences\Pages;

use App\Filament\Resources\Competences\CompetenceResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCompetences extends ManageRecords
{
    protected static string $resource = CompetenceResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
