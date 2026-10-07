<?php

namespace App\Filament\Actions;

use App\Models\Membre;
use App\Models\User;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

/**
 * Bouton « Voir en tant que », réservé au super administrateur.
 * Le lien est signé et ne vaut que 5 minutes.
 */
final class EnTantQueAction
{
    public static function pourCompte(): Action
    {
        return Action::make('enTantQue')
            ->label('Voir en tant que')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->hidden(fn (User $record): bool => $record->hasRole('super_administrateur') || ! self::autorise())
            ->url(fn (User $record): string => self::lien($record->id));
    }

    public static function pourMembre(): Action
    {
        return Action::make('enTantQueMembre')
            ->label('Voir en tant que')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->visible(fn (Membre $record): bool => $record->user_id !== null && self::autorise())
            ->url(fn (Membre $record): ?string => $record->user_id !== null ? self::lien($record->user_id) : null);
    }

    private static function autorise(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User && $utilisateur->hasRole('super_administrateur');
    }

    private static function lien(int $userId): string
    {
        return URL::temporarySignedRoute('en-tant-que', now()->addMinutes(5), ['user' => $userId]);
    }
}
