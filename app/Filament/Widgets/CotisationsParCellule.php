<?php

namespace App\Filament\Widgets;

use App\Models\Membre;
use App\Models\User;
use App\Services\CotisationService;
use Illuminate\Support\Facades\Auth;

/** Pour chaque cellule, la part des membres à jour de leurs cotisations. */
class CotisationsParCellule extends RepartitionWidget
{
    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User && $utilisateur->hasAnyRole(['administrateur', 'tresorier', 'responsable_section', 'suivi_evaluation']);
    }

    protected function titre(): string
    {
        return 'Membres à jour de cotisation, par cellule (%)';
    }

    protected function repartition(): array
    {
        $membres = $this->membresValides()->load('cellule');
        $retards = app(CotisationService::class)->retards($membres);

        return $membres
            ->groupBy(fn (Membre $membre): string => $membre->cellule->nom)
            ->map(function ($groupe) use ($retards): int {
                $aJour = $groupe->filter(fn (Membre $membre): bool => ($retards[$membre->id] ?? 0) === 0)->count();

                return $groupe->count() > 0 ? (int) round($aJour / $groupe->count() * 100) : 0;
            })
            ->sortKeys()
            ->all();
    }

    /** @return array<string, mixed> */
    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['min' => 0, 'max' => 100]],
        ];
    }
}
