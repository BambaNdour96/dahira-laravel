<?php

namespace App\Filament\Resources\Collectes\Pages;

use App\Filament\Resources\Collectes\CollecteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCollecte extends CreateRecord
{
    protected static string $resource = CollecteResource::class;

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
        return CollecteResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
