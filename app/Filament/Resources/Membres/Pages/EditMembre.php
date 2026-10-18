<?php

namespace App\Filament\Resources\Membres\Pages;

use App\Filament\Resources\Membres\Actions\CarteActions;
use App\Filament\Resources\Membres\Actions\ChangerStatutAction;
use App\Filament\Resources\Membres\Actions\FamilleActions;
use App\Filament\Resources\Membres\MembreResource;
use App\Filament\Resources\Membres\Widgets\CasquettesDuMembre;
use App\Models\Membre;
use App\Services\FamilleService;
use DomainException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditMembre extends EditRecord
{
    protected static string $resource = MembreResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [...ChangerStatutAction::toutes(), ...CarteActions::toutes(), FamilleActions::donnerSonCompte()];
    }

    /**
     * Les règles de la famille s'appliquent aussi à la modification (cellule du responsable, enfant rattaché).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $membre = $this->getRecord();

        try {
            return app(FamilleService::class)->rattacher($data, $membre instanceof Membre ? $membre : null);
        } catch (DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }

    /** @return array<int, class-string> */
    protected function getFooterWidgets(): array
    {
        return [CasquettesDuMembre::class];
    }
}
