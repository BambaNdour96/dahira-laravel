<?php

namespace App\Filament\Widgets;

use App\Enums\StatutProjet;
use App\Filament\Resources\Projets\ProjetResource;
use App\Models\Projet;
use App\Models\User;
use App\Policies\ProjetPolicy;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/** Sur le tableau de bord : chaque projet en cours, son avancement et son feu tricolore. */
class ProjetsEnCours extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 3;

    protected ?string $heading = 'Projets en cours';

    public static function canView(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User
            && $utilisateur->hasAnyRole(ProjetPolicy::LECTURE)
            && Projet::query()->where('statut', StatutProjet::EnCours->value)->exists();
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        return Projet::query()
            ->where('statut', StatutProjet::EnCours->value)
            ->orderBy('date_fin')
            ->limit(4)
            ->get()
            ->map(function (Projet $projet): Stat {
                [$sante, $raison] = $projet->diagnostic();

                return Stat::make($projet->titre, $projet->avancement().' %')
                    ->description($sante->getLabel().' · fin le '.$projet->date_fin->format('d/m/Y'))
                    ->color($sante->getColor())
                    ->url(ProjetResource::getUrl('view', ['record' => $projet]));
            })
            ->all();
    }
}
