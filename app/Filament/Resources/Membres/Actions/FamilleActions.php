<?php

namespace App\Filament\Resources\Membres\Actions;

use App\Models\Cellule;
use App\Models\ChangementCellule;
use App\Models\Membre;
use App\Models\User;
use App\Services\ChangementCelluleService;
use App\Services\FamilleService;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

final class FamilleActions
{
    /** Le Jewrine (ou le secrétariat) demande le changement de cellule d'un membre ; le super administrateur valide. */
    public static function demanderChangementCellule(): Action
    {
        return Action::make('changerCellule')
            ->label('Demander un changement de cellule')
            ->icon('heroicon-o-arrows-right-left')
            ->color('gray')
            ->visible(fn (Membre $record): bool => ($utilisateur = Auth::user()) instanceof User
                && app(ChangementCelluleService::class)->peutDemander($utilisateur, $record)
                && ! $record->changementsCellule()->where('statut', ChangementCellule::EN_ATTENTE)->exists())
            ->modalDescription('Le super administrateur valide la demande. Les proches dont le membre gère la fiche le suivent ; ses postes dans la cellule actuelle prennent fin.')
            ->schema([
                Select::make('cellule_id')->label('Nouvelle cellule')->required()
                    ->options(fn (Membre $record): array => Cellule::query()->enActivite()->whereKeyNot($record->cellule_id)->orderBy('nom')->pluck('nom', 'id')->all()),
                TextInput::make('motif')->label('Motif (facultatif)')->maxLength(255),
            ])
            ->action(function (Membre $record, array $data): void {
                $auteur = Auth::user();
                abort_unless($auteur instanceof User, 403);

                try {
                    app(ChangementCelluleService::class)->demander($record, Cellule::query()->whereKey($data['cellule_id'])->firstOrFail(), isset($data['motif']) ? (string) $data['motif'] : null, $auteur);
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Demande envoyée au super administrateur.')->success()->send();
            });
    }

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
