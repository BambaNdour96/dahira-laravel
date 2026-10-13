<?php

namespace App\Filament\Resources\Reunions\Pages;

use App\Filament\Resources\Reunions\ReunionResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditReunion extends EditRecord
{
    protected static string $resource = ReunionResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [ViewAction::make()->label('Ouvrir'), DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return ReunionResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
