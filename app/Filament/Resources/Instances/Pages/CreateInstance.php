<?php

namespace App\Filament\Resources\Instances\Pages;

use App\Filament\Resources\Instances\InstanceResource;
use App\Models\Instance;
use App\Services\ComiteService;
use Filament\Resources\Pages\CreateRecord;

class CreateInstance extends CreateRecord
{
    protected static string $resource = InstanceResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'cree_par' => auth()->id()];
    }

    protected function afterCreate(): void
    {
        $instance = $this->getRecord();

        if ($instance instanceof Instance) {
            $service = app(ComiteService::class);
            $service->preparerCdgmt($instance);
            $service->integrerResponsables($instance);
        }
    }

    protected function getRedirectUrl(): string
    {
        return InstanceResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
