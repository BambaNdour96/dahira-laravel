<?php

namespace App\Filament\Resources\Reunions\Pages;

use App\Filament\Resources\Reunions\ReunionResource;
use App\Models\Instance;
use App\Models\User;
use App\Services\ComiteService;
use Filament\Resources\Pages\CreateRecord;

class CreateReunion extends CreateRecord
{
    protected static string $resource = ReunionResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $utilisateur = auth()->user();
        $instance = Instance::query()->findOrFail((int) $data['instance_id']);
        abort_unless($utilisateur instanceof User && ComiteService::peutOrganiser($utilisateur, $instance), 403);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return ReunionResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
