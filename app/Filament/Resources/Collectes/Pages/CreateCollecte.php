<?php

namespace App\Filament\Resources\Collectes\Pages;

use App\Filament\Resources\Collectes\CollecteResource;
use App\Models\Collecte;
use App\Services\JourJService;
use Filament\Resources\Pages\CreateRecord;

class CreateCollecte extends CreateRecord
{
    protected static string $resource = CollecteResource::class;

    /** @var array{avec: bool, debut: mixed, fin: mixed, lieu: string|null} */
    protected array $jourJ = ['avec' => false, 'debut' => null, 'fin' => null, 'lieu' => null];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['cree_par'] = auth()->id();
        $this->jourJ = JourJService::extraire($data);

        return $data;
    }

    protected function afterCreate(): void
    {
        $collecte = $this->getRecord();

        if ($collecte instanceof Collecte) {
            app(JourJService::class)->synchroniser($collecte, $this->jourJ, is_numeric(auth()->id()) ? (int) auth()->id() : null);
        }
    }

    protected function getRedirectUrl(): string
    {
        return CollecteResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
