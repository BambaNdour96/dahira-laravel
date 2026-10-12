<?php

namespace App\Filament\Widgets;

use App\Enums\StatutCollecte;
use App\Filament\Resources\Collectes\CollecteResource;
use App\Models\Collecte;
use App\Models\User;
use App\Policies\CollectePolicy;
use App\Services\CollecteService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/** Sur le tableau de bord du bureau : chaque collecte ouverte, son avancement, un clic pour le détail. */
class CollectesEnCours extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 1;

    protected ?string $heading = 'Collectes en cours';

    public static function canView(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User
            && $utilisateur->hasAnyRole(CollectePolicy::LECTURE)
            && Collecte::query()->where('statut', StatutCollecte::Ouverte->value)->exists();
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        return Collecte::query()
            ->where('statut', StatutCollecte::Ouverte->value)
            ->orderByRaw('date_limite is null')
            ->orderBy('date_limite')
            ->limit(4)
            ->get()
            ->map(function (Collecte $collecte): Stat {
                $taux = $collecte->taux();

                return Stat::make($collecte->titre, $taux.' %')
                    ->description(CollecteService::fcfa($collecte->totalVerse()).' sur '.CollecteService::fcfa($collecte->cible())
                        .($collecte->date_limite !== null ? ' · limite '.$collecte->date_limite->format('d/m') : ''))
                    ->descriptionIcon('heroicon-m-gift')
                    ->color($taux >= 100 ? 'success' : ($taux >= 50 ? 'warning' : 'primary'))
                    ->url(CollecteResource::getUrl('view', ['record' => $collecte]));
            })
            ->all();
    }
}
