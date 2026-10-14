<?php

namespace App\Services;

use App\Enums\StatutMembre;
use App\Models\Cellule;
use App\Models\Evenement;
use App\Models\Membre;
use App\Models\Presence;
use App\Models\User;
use App\Notifications\RappelProgramme;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/** L'assiduité aux dahiras : taux par membre, absences de suite, alertes et rappels. */
final class AssiduiteService
{
    /**
     * Les dahiras passées d'une cellule, depuis une date.
     *
     * @return Collection<int, Evenement>
     */
    public function dahirasPassees(int $celluleId, Carbon $depuis): Collection
    {
        return Evenement::query()
            ->where('type', Evenement::DAHIRA)
            ->maintenus()
            ->where('cellule_id', $celluleId)
            ->where('debut', '>=', $depuis)
            ->where('debut', '<', now())
            ->orderByDesc('debut')
            ->get();
    }

    /**
     * L'assiduité d'un membre sur les 3 derniers mois.
     *
     * @return array{dahiras: int, presents: int, excuses: int, taux: int, absences_de_suite: int}
     */
    public function pour(Membre $membre, int $mois = 3): array
    {
        $depuis = now()->subMonths($mois);

        if ($membre->valide_le !== null && $membre->valide_le->greaterThan($depuis)) {
            $depuis = $membre->valide_le->copy();
        }

        $dahiras = $this->dahirasPassees($membre->cellule_id, $depuis);
        $statuts = Presence::query()
            ->whereIn('evenement_id', $dahiras->modelKeys())
            ->where('membre_id', $membre->id)
            ->pluck('statut', 'evenement_id');

        $presents = $statuts->filter(fn (mixed $statut): bool => $statut === 'present')->count();
        $excuses = $statuts->filter(fn (mixed $statut): bool => $statut === 'excuse')->count();

        $deSuite = 0;
        foreach ($dahiras as $dahira) {
            if ($statuts->has($dahira->id)) {
                break;
            }
            $deSuite++;
        }

        $comptees = $dahiras->count() - $excuses;

        return [
            'dahiras' => $dahiras->count(),
            'presents' => $presents,
            'excuses' => $excuses,
            'taux' => $comptees > 0 ? (int) round($presents / $comptees * 100) : 100,
            'absences_de_suite' => $deSuite,
        ];
    }

    /** La prochaine dahira de la cellule du membre. */
    public function prochaineDahira(Membre $membre): ?Evenement
    {
        return Evenement::query()
            ->where('type', Evenement::DAHIRA)
            ->maintenus()
            ->where('cellule_id', $membre->cellule_id)
            ->where('debut', '>=', now()->subHours(2))
            ->orderBy('debut')
            ->first();
    }

    /**
     * Chaque matin : rappel des dahiras du jour, et alerte sur les membres absents 3 dahiras de suite.
     *
     * @return array{rappels: int, alertes: int}
     */
    public function routineDuMatin(): array
    {
        $rappels = 0;
        $alertes = 0;

        $duJour = Evenement::query()
            ->where('type', Evenement::DAHIRA)
            ->maintenus()
            ->where('avec_rappel', true)
            ->whereDate('debut', now())
            ->with('cellule')
            ->get();

        foreach ($duJour as $dahira) {
            $comptes = User::query()->whereIn('id', Membre::query()
                ->where('statut', StatutMembre::Valide->value)
                ->where('cellule_id', $dahira->cellule_id)
                ->whereNotNull('user_id')
                ->select('user_id'))->get();

            $message = 'Dahira de la cellule '.($dahira->cellule->nom ?? '').' aujourd\'hui à '.$dahira->debut->format('H\hi').', '.$dahira->lieu
                .($dahira->description ? '. Programme : '.$dahira->description : '').'. Pensez à votre carte de membre.';

            foreach ($comptes as $compte) {
                $compte->notify(new RappelProgramme('Dahira de la cellule', $message, route('carte'), 'Voir ma carte'));
                $rappels++;
            }
        }

        $hier = Evenement::query()
            ->where('type', Evenement::DAHIRA)
            ->maintenus()
            ->whereDate('debut', now()->subDay())
            ->with('cellule')
            ->get();

        foreach ($hier as $dahira) {
            $cellule = $dahira->cellule;

            if ($cellule === null) {
                continue;
            }

            $membres = Membre::query()->where('statut', StatutMembre::Valide->value)->where('cellule_id', $cellule->id)->get();

            foreach ($membres as $membre) {
                if ($this->pour($membre)['absences_de_suite'] === 3) {
                    $this->alerter($cellule, $membre);
                    $alertes++;
                }
            }
        }

        app(SympathisantService::class)->purger();

        return ['rappels' => $rappels, 'alertes' => $alertes];
    }

    private function alerter(Cellule $cellule, Membre $membre): void
    {
        // Le Jewrine de cellule est responsable de tous ses membres, Goor Yalla et Sokhnas.
        $destinataires = array_filter([$cellule->jewrine()?->id]);

        $comptes = User::query()->whereIn('id', $destinataires)->get();

        if ($comptes->isNotEmpty()) {
            Notification::make()
                ->title($membre->prenom.' '.$membre->nom.' a manqué 3 dahiras de suite')
                ->body('Cellule '.$cellule->nom.'. Prenez de ses nouvelles.')
                ->warning()
                ->sendToDatabase($comptes);
        }
    }
}
