<?php

namespace App\Filament\Resources\Rapports\Pages;

use App\Filament\Resources\Rapports\RapportResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRapport extends CreateRecord
{
    protected static string $resource = RapportResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['auteur_id'] = auth()->id();
        $data['statut'] = 'brouillon';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return RapportResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
