<?php

namespace App\Filament\Resources\Seances\Pages;

use App\Filament\Resources\Seances\SeanceResource;
use App\Models\Cellule;
use App\Models\Evenement;
use Filament\Resources\Pages\CreateRecord;

class CreateSeance extends CreateRecord
{
    protected static string $resource = SeanceResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        abort_unless(array_key_exists((int) $data['cellule_id'], SeanceResource::cellulesPermises()), 403);

        $nom = Cellule::query()->whereKey((int) $data['cellule_id'])->value('nom');

        return [...$data, 'type' => Evenement::SEANCE, 'titre' => 'Dahira de la cellule '.$nom];
    }

    protected function getRedirectUrl(): string
    {
        return SeanceResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
