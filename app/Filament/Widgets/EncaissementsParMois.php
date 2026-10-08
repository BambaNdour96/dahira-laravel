<?php

namespace App\Filament\Widgets;

use App\Enums\StatutPaiement;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class EncaissementsParMois extends RepartitionWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User && $utilisateur->hasAnyRole(['administrateur', 'tresorier']);
    }

    protected function titre(): string
    {
        return 'Encaissements des 12 derniers mois (FCFA)';
    }

    protected function repartition(): array
    {
        $debut = now()->subMonths(11)->startOfMonth();
        $mois = [];

        for ($i = 0; $i < 12; $i++) {
            $date = $debut->copy()->addMonths($i);
            $mois[$date->format('Y-m')] = ['libelle' => $date->translatedFormat('M Y'), 'total' => 0];
        }

        $paiements = Paiement::query()
            ->where('statut', StatutPaiement::Confirme->value)
            ->where('traite_le', '>=', $debut)
            ->get(['traite_le', 'montant']);

        foreach ($paiements as $paiement) {
            $cle = $paiement->traite_le?->format('Y-m');

            if ($cle !== null && isset($mois[$cle])) {
                $mois[$cle]['total'] += $paiement->montant;
            }
        }

        return collect($mois)->mapWithKeys(fn (array $m): array => [$m['libelle'] => $m['total']])->all();
    }
}
