<?php

namespace App\Filament\Resources\ComptesBureau\Pages;

use App\Filament\Resources\ComptesBureau\CompteBureauResource;
use App\Models\User;
use App\Services\CompteBureauService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageComptesBureau extends ManageRecords
{
    protected static string $resource = CompteBureauResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nouveau compte')
                ->using(fn (array $data): User => app(CompteBureauService::class)->creer($data)),
        ];
    }
}
