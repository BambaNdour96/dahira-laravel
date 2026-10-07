<?php

namespace App\Filament\Widgets;

use App\Enums\StatutPaiement;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class EncaissementsParCellule extends RepartitionWidget
{
    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User && $utilisateur->hasAnyRole(['administrateur', 'tresorier']);
    }

    protected function titre(): string
    {
        return 'Encaissements de l\'année par cellule (FCFA)';
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function repartition(): array
    {
        return Paiement::query()
            ->with('membre.cellule')
            ->where('statut', StatutPaiement::Confirme->value)
            ->where('traite_le', '>=', now()->startOfYear())
            ->get()
            ->groupBy(fn (Paiement $paiement): string => $paiement->membre->cellule->nom)
            ->map(fn ($paiements): int => (int) $paiements->sum('montant'))
            ->sortKeys()
            ->all();
    }
}
