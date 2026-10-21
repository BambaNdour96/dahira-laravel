<?php

namespace App\Filament\Resources\Instances\RelationManagers;

use App\Models\Instance;
use App\Models\User;
use App\Services\ComiteService;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Un onglet de l'espace d'un comité d'organisation : visible par qui peut lire le comité. */
abstract class OngletComite extends RelationManager
{
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $utilisateur = Auth::user();

        return $ownerRecord instanceof Instance && $ownerRecord->estComiteOrganisation()
            && $utilisateur instanceof User && ComiteService::peutLire($utilisateur, $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    protected static function utilisateur(): ?User
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User ? $utilisateur : null;
    }

    protected function comite(): Instance
    {
        $comite = $this->getOwnerRecord();
        abort_unless($comite instanceof Instance, 404);

        return $comite;
    }
}
