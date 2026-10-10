<?php

namespace App\Filament\Widgets;

use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Filament\Resources\Membres\MembreResource;
use App\Models\DemandeSuppression;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\RegleValidation;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Les tâches de la personne connectée, en haut du tableau de bord.
 */
class MesTaches extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return self::taches() !== [];
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        return self::taches();
    }

    /** @return array<Stat> */
    private static function taches(): array
    {
        $utilisateur = Auth::user();

        if (! $utilisateur instanceof User) {
            return [];
        }

        $taches = [];

        if ($utilisateur->hasRole('responsable_section')) {
            $demandes = MembreResource::getEloquentQuery()->where('statut', StatutMembre::EnAttente->value);
            $taches[] = self::tache('Demandes à examiner', (clone $demandes)->count(), (clone $demandes)->min('updated_at'), '/admin/membres');
        }

        if ($utilisateur->hasAnyRole(['tresorier', 'super_administrateur'])) {
            $paiements = Paiement::query()
                ->where('statut', StatutPaiement::Declare->value)
                ->whereDoesntHave('membre', fn ($membre) => $membre->whereIn('poste_id', RegleValidation::query()->select('poste_id')));
            $taches[] = self::tache('Paiements à vérifier', (clone $paiements)->count(), (clone $paiements)->min('declare_le'), '/admin/paiements');
        }

        $poste = $utilisateur->membre?->poste_id;

        if ($poste !== null) {
            $postesSuivis = RegleValidation::query()->get()
                ->filter(fn (RegleValidation $regle): bool => in_array((int) $poste, $regle->idsValidateurs(), true))
                ->pluck('poste_id')
                ->all();

            if ($postesSuivis !== []) {
                $bureau = Paiement::query()
                    ->where('statut', StatutPaiement::Declare->value)
                    ->whereHas('membre', fn ($membre) => $membre->whereIn('poste_id', $postesSuivis)->where('user_id', '!=', $utilisateur->id));
                $taches[] = self::tache('Paiements du bureau à valider', (clone $bureau)->count(), (clone $bureau)->min('declare_le'), '/admin/paiements');
            }
        }

        if ($utilisateur->hasRole('super_administrateur')) {
            $suppressions = DemandeSuppression::query()->where('statut', DemandeSuppression::EN_ATTENTE);
            $taches[] = self::tache('Demandes de suppression', (clone $suppressions)->count(), (clone $suppressions)->min('created_at'), '/admin/demandes-suppression');

            $limite = now()->subDays(3);
            $enRetard = Membre::query()->where('statut', StatutMembre::EnAttente->value)->where('updated_at', '<', $limite)->count()
                + Paiement::query()->where('statut', StatutPaiement::Declare->value)->where('declare_le', '<', $limite)->count();
            $taches[] = Stat::make('Tâches en retard (plus de 3 jours)', (string) $enRetard)
                ->description($enRetard > 0 ? 'Demandes et paiements de tout le bureau' : 'Aucune')
                ->color($enRetard > 0 ? 'danger' : 'success');
        }

        return $taches;
    }

    private static function tache(string $libelle, int $nombre, mixed $plusAncienne, string $lien): Stat
    {
        $description = $nombre > 0 && $plusAncienne !== null
            ? 'La plus ancienne : '.Carbon::parse((string) $plusAncienne)->diffForHumans()
            : 'Rien en attente';

        return Stat::make($libelle, (string) $nombre)
            ->description($description)
            ->color($nombre > 0 ? 'warning' : 'success')
            ->url(url($lien));
    }
}
