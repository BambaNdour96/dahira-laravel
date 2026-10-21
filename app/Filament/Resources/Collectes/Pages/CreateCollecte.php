<?php

namespace App\Filament\Resources\Collectes\Pages;

use App\Filament\Resources\Collectes\CollecteResource;
use App\Models\User;
use App\Services\ProgrammeService;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateCollecte extends CreateRecord
{
    protected static string $resource = CollecteResource::class;

    protected static ?string $title = 'Créer un événement';

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        $auteur = Auth::user();
        abort_unless($auteur instanceof User, 403);

        try {
            return app(ProgrammeService::class)->enregistrer($data, null, $auteur);
        } catch (DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return CollecteResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
