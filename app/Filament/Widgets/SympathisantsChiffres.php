<?php

namespace App\Filament\Widgets;

use App\Models\Sympathisant;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/** Les sympathisants : combien viennent, combien sont à inviter, combien sont devenus membres. */
class SympathisantsChiffres extends StatsOverviewWidget
{
    protected static ?int $sort = 7;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Sympathisants';

    public static function canView(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User
            && $utilisateur->hasAnyRole(['super_administrateur', 'jewrine_national', 'secretaire_general', 'suivi_evaluation'])
            && Sympathisant::query()->exists();
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $total = Sympathisant::query()->auRegistre()->count();
        $actifs = Sympathisant::query()->auRegistre()->whereNull('membre_id')->where('derniere_visite_le', '>=', now()->subMonths(3))->count();
        $nouveaux = Sympathisant::query()->where('created_at', '>=', now()->startOfMonth())->count();
        $convertis = Sympathisant::query()->whereNotNull('membre_id')->count();
        $aInviter = Sympathisant::query()
            ->auRegistre()
            ->whereNull('membre_id')
            ->whereRaw('(select count(*) from visites_sympathisants v where v.sympathisant_id = sympathisants.id and v.created_at >= ?) >= ?', [now()->subMonths(3), Sympathisant::VISITES_POUR_INVITER])
            ->count();

        return [
            Stat::make('Actifs', (string) $actifs)->description('venus ces 3 derniers mois'),
            Stat::make('Nouveaux ce mois', (string) $nouveaux),
            Stat::make('À inviter', (string) $aInviter)->description('3 visites en 3 mois')->color($aInviter > 0 ? 'warning' : 'gray'),
            Stat::make('Devenus membres', (string) $convertis)
                ->description(($total > 0 ? (int) round($convertis / $total * 100) : 0).' % des sympathisants recensés')
                ->color('success'),
        ];
    }
}
