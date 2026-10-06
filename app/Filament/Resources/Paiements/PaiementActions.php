<?php

namespace App\Filament\Resources\Paiements;

use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\User;
use App\Services\PaiementService;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class PaiementActions
{
    public static function confirmer(): Action
    {
        return Action::make('confirmer')
            ->label('Confirmer')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Paiement $record): bool => $record->statut === StatutPaiement::Declare
                && Gate::allows('update', $record))
            ->requiresConfirmation()
            ->modalDescription(fn (Paiement $record): string => 'Vérifiez d\'abord dans Wave Business le paiement de '
                .number_format($record->montant, 0, ',', ' ').' FCFA, référence '.$record->reference
                .'. Le membre sera validé et recevra sa carte.')
            ->action(function (Paiement $record): void {
                if (! Gate::allows('update', $record)) {
                    return;
                }

                try {
                    app(PaiementService::class)->confirmer($record, self::auteur());
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Paiement confirmé : le membre a reçu sa carte.')->success()->send();
            });
    }

    public static function refuser(): Action
    {
        return Action::make('refuser')
            ->label('Refuser')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Paiement $record): bool => $record->statut === StatutPaiement::Declare
                && Gate::allows('update', $record))
            ->schema([
                Textarea::make('motif')
                    ->label('Motif (visible par le membre)')
                    ->placeholder('Exemple : référence introuvable dans Wave Business')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (Paiement $record, array $data): void {
                if (! Gate::allows('update', $record)) {
                    return;
                }

                try {
                    app(PaiementService::class)->refuser($record, self::auteur(), (string) $data['motif']);
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Déclaration refusée. Le membre pourra déclarer à nouveau.')->success()->send();
            });
    }

    public static function especes(): Action
    {
        return Action::make('paiementEspeces')
            ->label('Paiement en espèces')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (Membre $record): bool => in_array($record->statut, [StatutMembre::Accepte, StatutMembre::Expire], true)
                && Gate::allows('create', Paiement::class))
            ->requiresConfirmation()
            ->modalDescription(fn (): string => 'Confirmez avoir reçu '
                .number_format(app(PaiementService::class)->montantFraisCarte(), 0, ',', ' ')
                .' FCFA en espèces. Le membre sera validé et recevra sa carte.')
            ->action(function (Membre $record): void {
                if (! Gate::allows('create', Paiement::class)) {
                    return;
                }

                try {
                    app(PaiementService::class)->enregistrerEspeces($record, self::auteur());
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Paiement enregistré : le membre a reçu sa carte.')->success()->send();
            });
    }

    private static function auteur(): ?User
    {
        $auteur = Auth::user();

        return $auteur instanceof User ? $auteur : null;
    }
}
