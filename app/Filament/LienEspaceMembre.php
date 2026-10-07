<?php

namespace App\Filament;

use App\Models\User;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\Auth;

/**
 * Lien du panneau vers « Mon compte » : un membre du bureau reste un membre.
 */
final class LienEspaceMembre
{
    public static function item(): NavigationItem
    {
        return NavigationItem::make('Mon compte de membre')
            ->url('/mon-espace')
            ->icon('heroicon-o-identification')
            ->sort(-1)
            ->visible(function (): bool {
                $utilisateur = Auth::user();

                return $utilisateur instanceof User && $utilisateur->membre()->exists();
            });
    }
}
