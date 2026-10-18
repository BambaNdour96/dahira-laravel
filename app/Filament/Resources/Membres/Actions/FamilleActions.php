<?php

namespace App\Filament\Resources\Membres\Actions;

use App\Models\Membre;
use App\Models\User;
use App\Services\FamilleService;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

final class FamilleActions
{
    /** Le Jewrine de cellule donne son propre compte à un proche majeur ; le mot de passe provisoire s'affiche une fois. */
    public static function donnerSonCompte(): Action
    {
        return Action::make('donnerSonCompte')
            ->label('Lui donner son propre compte')
            ->icon('heroicon-o-key')
            ->color('gray')
            ->visible(fn (Membre $record): bool => ($utilisateur = Auth::user()) instanceof User
                && ! $record->estEnfant()
                && app(FamilleService::class)->peutDonnerSonCompte($utilisateur, $record))
            ->modalDescription('Le proche se connectera avec son propre numéro et gardera sa fiche, sa carte et tout son historique. Le membre qui gérait sa fiche est prévenu.')
            ->fillForm(fn (Membre $record): array => ['telephone' => $record->telephone])
            ->schema([TextInput::make('telephone')->label('Son numéro de téléphone')->tel()->required()])
            ->action(function (Membre $record, array $data): void {
                $auteur = Auth::user();
                abort_unless($auteur instanceof User, 403);

                try {
                    $motDePasse = app(FamilleService::class)->donnerSonCompte($record, (string) $data['telephone'], $auteur);
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title($record->prenom.' a maintenant son propre compte.')
                    ->body('Mot de passe provisoire à lui transmettre : '.$motDePasse.' (il le changera à la première connexion).')
                    ->persistent()
                    ->success()
                    ->send();
            });
    }
}
