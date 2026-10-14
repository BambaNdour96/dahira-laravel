<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\Capacites;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/** La matrice des droits, en lecture : ce que chaque casquette permet (voir App\Support\Capacites). */
class MatriceDesDroits extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Matrice des droits';

    protected static ?string $title = 'Matrice des droits';

    protected static ?string $slug = 'matrice-des-droits';

    protected string $view = 'filament.matrice-des-droits';

    public static function canAccess(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User && $utilisateur->can('plateforme.administrer');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'casquettes' => Capacites::CASQUETTES,
            'catalogue' => Capacites::CATALOGUE,
        ];
    }
}
