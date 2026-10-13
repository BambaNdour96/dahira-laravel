<?php

namespace App\Filament\Resources\Instances\Pages;

use App\Filament\Resources\Instances\InstanceResource;
use App\Models\Instance;
use App\Services\ComiteService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditInstance extends EditRecord
{
    protected static string $resource = InstanceResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [ViewAction::make()->label('Ouvrir'), DeleteAction::make()];
    }

    protected function afterSave(): void
    {
        $instance = $this->getRecord();

        if ($instance instanceof Instance) {
            app(ComiteService::class)->integrerResponsables($instance);
        }
    }
}
