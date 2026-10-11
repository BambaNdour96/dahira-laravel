<?php

namespace App\Filament\Widgets;

use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Filament\Resources\Membres\MembreResource;
use App\Filament\Resources\Relances\RelanceResource;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\User;
use App\Services\CotisationService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/** Le suivi des cotisations mensuelles : encaissé ce mois, montant en retard, retardataires. */
class CotisationsMensuelles extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 2;

    protected ?string $heading = 'Cotisations mensuelles';

    public static function canView(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User && $utilisateur->hasAnyRole(['administrateur', 'tresorier', 'responsable_section']);
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $service = app(CotisationService::class);
        $mensuel = $service->montantMensuel();

        $valides = Membre::query()
            ->whereIn('id', MembreResource::getEloquentQuery()->select('membres.id'))
            ->where('statut', StatutMembre::Valide->value)
            ->get();

        $retards = $service->retards($valides);
        $retardataires = count(array_filter($retards, fn (int $nombre): bool => $nombre > 0));
        $moisDus = array_sum($retards);

        $ceMois = (int) Paiement::query()
            ->whereIn('membre_id', $valides->modelKeys())
            ->where('objet', 'cotisation')
            ->where('statut', StatutPaiement::Confirme->value)
            ->where('traite_le', '>=', now()->startOfMonth())
            ->sum('montant');

        $attendu = count($retards) * $mensuel;
        $fcfa = fn (int $montant): string => number_format($montant, 0, ',', ' ').' FCFA';

        $retard = Stat::make('Retardataires', (string) $retardataires)
            ->description($retardataires > 0 ? 'Membres à relancer' : 'Tout le monde est à jour')
            ->descriptionIcon('heroicon-m-bell-alert')
            ->color($retardataires > 0 ? 'danger' : 'success');

        if (RelanceResource::canViewAny()) {
            $retard->url(RelanceResource::getUrl());
        }

        return [
            Stat::make('Encaissé ce mois', $fcfa($ceMois))
                ->description($attendu > 0 ? 'Attendu chaque mois : '.$fcfa($attendu) : 'Aucun membre validé')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($attendu > 0 && $ceMois >= $attendu ? 'success' : 'primary'),
            Stat::make('Montant en retard', $fcfa($moisDus * $mensuel))
                ->description($moisDus.' mois de cotisation en attente')
                ->descriptionIcon('heroicon-m-clock')
                ->color($moisDus > 0 ? 'warning' : 'success'),
            $retard,
        ];
    }
}
