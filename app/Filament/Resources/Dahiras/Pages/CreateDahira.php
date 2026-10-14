<?php

namespace App\Filament\Resources\Dahiras\Pages;

use App\Filament\Resources\Dahiras\DahiraResource;
use App\Models\Cellule;
use App\Models\Evenement;
use Filament\Resources\Pages\CreateRecord;

class CreateDahira extends CreateRecord
{
    protected static string $resource = DahiraResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        abort_unless(array_key_exists((int) $data['cellule_id'], DahiraResource::cellulesPermises()), 403);

        $nom = Cellule::query()->whereKey((int) $data['cellule_id'])->value('nom');

        return [...$data, 'type' => Evenement::DAHIRA, 'titre' => 'Dahira de la cellule '.$nom];
    }

    protected function getRedirectUrl(): string
    {
        return DahiraResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
