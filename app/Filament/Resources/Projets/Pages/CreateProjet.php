<?php

namespace App\Filament\Resources\Projets\Pages;

use App\Filament\Resources\Projets\ProjetResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProjet extends CreateRecord
{
    protected static string $resource = ProjetResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['cree_par'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return ProjetResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
