<?php

namespace App\Filament\Resources\Collectes\Pages;

use App\Filament\Resources\Collectes\CollecteResource;
use App\Models\Collecte;
use App\Services\JourJService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCollecte extends EditRecord
{
    protected static string $resource = CollecteResource::class;

    /** @var array{avec: bool, debut: mixed, fin: mixed, lieu: string|null} */
    protected array $jourJ = ['avec' => false, 'debut' => null, 'fin' => null, 'lieu' => null];

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('Tableau de bord'),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $collecte = $this->getRecord();

        return $collecte instanceof Collecte ? JourJService::remplir($collecte, $data) : $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->jourJ = JourJService::extraire($data);

        return $data;
    }

    protected function afterSave(): void
    {
        $collecte = $this->getRecord();

        if ($collecte instanceof Collecte) {
            app(JourJService::class)->synchroniser($collecte->refresh(), $this->jourJ, is_numeric(auth()->id()) ? (int) auth()->id() : null);
        }
    }
}
