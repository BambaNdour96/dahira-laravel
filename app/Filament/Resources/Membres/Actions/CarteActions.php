<?php

namespace App\Filament\Resources\Membres\Actions;

use App\Models\Membre;
use App\Models\User;
use App\Services\CarteService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class CarteActions
{
    /** @return list<Action> */
    public static function toutes(): array
    {
        return [
            Action::make('voirVerification')
                ->label('Page de vérification')
                ->icon('heroicon-o-qr-code')
                ->color('gray')
                ->visible(fn (Membre $record): bool => $record->carteActive !== null)
                ->url(fn (Membre $record): ?string => $record->carteActive
                    ? route('verification', $record->carteActive->jeton)
                    : null)
                ->openUrlInNewTab(),

            Action::make('revoquerCarte')
                ->label('Révoquer la carte')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->visible(fn (Membre $record): bool => $record->carteActive !== null
                    && Gate::allows('revoquerCarte', $record))
                ->requiresConfirmation()
                ->modalDescription('L\'ancienne carte deviendra invalide immédiatement, et une nouvelle carte sera émise.')
                ->schema([
                    Textarea::make('motif')
                        ->label('Motif')
                        ->placeholder('Exemple : carte perdue')
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (Membre $record, array $data): void {
                    $carte = $record->carteActive;

                    if ($carte === null || ! Gate::allows('revoquerCarte', $record)) {
                        return;
                    }

                    $auteur = Auth::user();
                    app(CarteService::class)->revoquer($carte, $auteur instanceof User ? $auteur : null, (string) $data['motif']);

                    Notification::make()->title('Carte révoquée. Une nouvelle carte a été émise.')->success()->send();
                }),
        ];
    }
}
