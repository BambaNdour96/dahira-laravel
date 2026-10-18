<?php

namespace App\Filament\Resources\Membres\Pages;

use App\Filament\Resources\Membres\MembreResource;
use App\Models\User;
use App\Services\FamilleService;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Le Jewrine de cellule inscrit lui-même un membre, par exemple sans téléphone, ou l'enfant d'un membre. */
class CreateMembre extends CreateRecord
{
    protected static string $resource = MembreResource::class;

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        $auteur = Auth::user();
        abort_unless($auteur instanceof User, 403);

        try {
            return app(FamilleService::class)->inscrireParLeJewrine($data, $auteur);
        } catch (DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }
}
