<?php

namespace App\Filament\Resources\Collectes\Pages;

use App\Filament\Resources\Collectes\CollecteResource;
use App\Models\Collecte;
use App\Models\User;
use App\Services\JourJService;
use App\Services\ProgrammeService;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditCollecte extends EditRecord
{
    protected static string $resource = CollecteResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('Tableau de bord'),
            CollecteResource::annuler(),
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

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $auteur = Auth::user();
        abort_unless($auteur instanceof User && $record instanceof Collecte, 403);

        try {
            return app(ProgrammeService::class)->enregistrer($data, $record, $auteur);
        } catch (DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
            throw new Halt;
        }
    }
}
