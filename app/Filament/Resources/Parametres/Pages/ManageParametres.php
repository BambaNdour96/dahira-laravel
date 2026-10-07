<?php

namespace App\Filament\Resources\Parametres\Pages;

use App\Filament\Resources\Parametres\ParametreResource;
use App\Models\Parametre;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageParametres extends ManageRecords
{
    protected static string $resource = ParametreResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        $active = Parametre::valeur('maintenance') === '1';

        return [
            Action::make('maintenance')
                ->label($active ? 'Désactiver le mode maintenance' : 'Activer le mode maintenance')
                ->icon($active ? 'heroicon-o-play' : 'heroicon-o-wrench-screwdriver')
                ->color($active ? 'success' : 'danger')
                ->requiresConfirmation()
                ->modalDescription($active
                    ? 'Le site rouvre pour tous les membres.'
                    : 'Le site des membres affichera « Maintenance en cours ». Vous, le panneau du bureau et la vérification des cartes restez accessibles.')
                ->action(function () use ($active): void {
                    Parametre::query()->updateOrCreate(
                        ['cle' => 'maintenance'],
                        ['libelle' => 'Mode maintenance', 'valeur' => $active ? '0' : '1'],
                    );

                    Notification::make()
                        ->title($active ? 'Le site est rouvert aux membres.' : 'Mode maintenance activé.')
                        ->success()
                        ->send();

                    $this->redirect(ParametreResource::getUrl('index'));
                }),
        ];
    }
}
