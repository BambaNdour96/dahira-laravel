<?php

namespace App\Filament;

use App\Models\User;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\Auth;

/**
 * Liens du panneau vers l'espace personnel.
 */
final class LienEspaceMembre
{
    /** « Mon compte de membre » : un membre du bureau reste un membre. */
    public static function item(): NavigationItem
    {
        return NavigationItem::make('Mon compte de membre')
            ->url('/mon-espace')
            ->icon('heroicon-o-identification')
            ->sort(-2)
            ->visible(function (): bool {
                $utilisateur = Auth::user();

                return $utilisateur instanceof User && $utilisateur->membre()->exists();
            });
    }

    /** « Changer mon mot de passe », pour tous les comptes du panneau. */
    public static function motDePasse(): NavigationItem
    {
        return NavigationItem::make('Changer mon mot de passe')
            ->url('/mon-compte/mot-de-passe')
            ->icon('heroicon-o-lock-closed')
            ->sort(-1);
    }
}
