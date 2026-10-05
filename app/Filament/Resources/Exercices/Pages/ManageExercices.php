<?php

namespace App\Filament\Resources\Exercices\Pages;

use App\Filament\Resources\Exercices\ExerciceResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExercices extends ManageRecords
{
    protected static string $resource = ExerciceResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
