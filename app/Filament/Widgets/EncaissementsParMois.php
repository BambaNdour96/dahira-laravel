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

        return $utilisateur instanceof User && $utilisateur->hasAnyRole(['administrateur', 'tresorier', 'suivi_evaluation']);
    }

    protected function titre(): string
    {
        return 'Encaissements des 12 derniers mois, par nature (FCFA)';
    }

    /** Total par mois, toutes natures confondues (utilisé aussi pour l'export). */
    protected function repartition(): array
    {
        return collect($this->parNature())
            ->map(fn (array $natures): int => array_sum($natures))
            ->all();
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $mois = $this->parNature();
        $natures = [
            'frais_carte' => ['Frais de carte', '#d4a017'],
            'cotisation' => ['Cotisations', '#1e5223'],
            'collecte' => ['Participations (collectes)', '#58ad5e'],
        ];

        return [
            'datasets' => collect($natures)->map(fn (array $nature, string $objet): array => [
                'label' => $nature[0],
                'data' => array_values(array_map(fn (array $totaux): int => $totaux[$objet], $mois)),
                'backgroundColor' => $nature[1],
                'borderColor' => $nature[1],
            ])->values()->all(),
            'labels' => array_keys($mois),
        ];
    }

    /** @return array<string, mixed> */
    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => true]],
            'scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true]],
        ];
    }

    /** @return array<string, array{frais_carte: int, cotisation: int, collecte: int}> */
    private function parNature(): array
    {
        $debut = now()->subMonths(11)->startOfMonth();
        $mois = [];

        for ($i = 0; $i < 12; $i++) {
            $date = $debut->copy()->addMonths($i);
            $mois[$date->format('Y-m')] = ['libelle' => $date->translatedFormat('M Y'), 'frais_carte' => 0, 'cotisation' => 0, 'collecte' => 0];
        }

        $paiements = Paiement::query()
            ->where('statut', StatutPaiement::Confirme->value)
            ->where('traite_le', '>=', $debut)
            ->get(['traite_le', 'montant', 'objet']);

        foreach ($paiements as $paiement) {
            $cle = $paiement->traite_le?->format('Y-m');
            $objet = in_array($paiement->objet, ['cotisation', 'collecte'], true) ? $paiement->objet : 'frais_carte';

            if ($cle !== null && isset($mois[$cle])) {
                $mois[$cle][$objet] += $paiement->montant;
            }
        }

        return collect($mois)->mapWithKeys(fn (array $m): array => [
            $m['libelle'] => ['frais_carte' => $m['frais_carte'], 'cotisation' => $m['cotisation'], 'collecte' => $m['collecte']],
        ])->all();
    }
}
