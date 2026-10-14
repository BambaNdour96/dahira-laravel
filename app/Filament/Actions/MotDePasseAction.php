<?php

namespace App\Filament\Actions;

use App\Models\Membre;
use App\Services\CasquettesService;
use App\Services\CompteService;
use App\Support\AffichageMotDePasse;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

/**
 * Le Jewrine de la cellule ou un administrateur donne un mot de passe provisoire à un membre.
 */
final class MotDePasseAction
{
    public static function pourMembre(): Action
    {
        return Action::make('nouveauMotDePasse')
            ->label('Nouveau mot de passe')
            ->icon('heroicon-o-key')
            ->color('gray')
            ->visible(fn (Membre $record): bool => $record->user !== null
                && ! CasquettesService::estSuperAdministrateur($record->user)
                && Gate::allows('update', $record))
            ->requiresConfirmation()
            ->modalDescription('Un mot de passe provisoire sera généré. Le membre devra en choisir un nouveau à sa prochaine connexion.')
            ->action(function (Membre $record): void {
                $utilisateur = $record->user;

                if ($utilisateur === null || ! Gate::allows('update', $record)) {
                    return;
                }

                $motDePasse = app(CompteService::class)->reinitialiserMotDePasse($utilisateur);

                Notification::make()
                    ->title('Mot de passe provisoire de '.$record->prenom.' '.$record->nom)
                    ->body(AffichageMotDePasse::corps($motDePasse))
                    ->success()
                    ->persistent()
                    ->send();
            });
    }
}
