<?php

namespace App\Services;

use App\Models\Collecte;
use App\Models\Engagement;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\Rappel;
use App\Models\User;
use App\Notifications\RappelProgramme;
use Illuminate\Support\Carbon;

/**
 * Les rappels des programmes : utiles sans être pénibles.
 * - une fenêtre à l'ouverture de l'application, la plus urgente, au plus une fois par jour et par programme ;
 * - des notifications à des moments choisis, jamais de nuit, au plus une tous les 3 jours (sauf veille et jour J) ;
 * - des messages d'encouragement quand un membre est à jour ou que la Daara franchit un cap.
 */
final class RappelService
{
    public function __construct(private readonly CollecteService $collectes) {}

    /**
     * Le rappel à montrer à l'ouverture de l'application, ou rien.
     *
     * @return array{titre: string, message: string, bouton: string, url: string}|null
     */
    public function fenetre(Membre $membre): ?array
    {
        $candidats = [];

        foreach ($this->collectes->ouvertesPour($membre) as $collecte) {
            if (! $collecte->rappels_actifs || $this->dejaEnvoye($collecte, $membre, 'fenetre', now()->startOfDay())) {
                continue;
            }

            $situation = $this->situation($collecte, $membre);

            if ($situation !== null) {
                $candidats[] = [$situation['priorite'], $collecte, $situation];
            }
        }

        if ($candidats === []) {
            return null;
        }

        usort($candidats, fn (array $a, array $b): int => $a[0] <=> $b[0]);
        [, $collecte, $situation] = $candidats[0];

        $this->noter($collecte, $membre, 'fenetre', $situation['type']);

        return [
            'titre' => $collecte->titre,
            'message' => $situation['message'],
            'bouton' => $situation['bouton'],
            'url' => $situation['url'],
        ];
    }

    /** Envoie les notifications du jour ; renvoie le nombre de rappels envoyés. */
    public function envoyer(?Carbon $maintenant = null): int
    {
        $maintenant ??= now();

        if ($maintenant->hour < 8 || $maintenant->hour >= 21) {
            return 0;
        }

        $envoyes = 0;

        $collectes = Collecte::query()
            ->where('statut', 'ouverte')
            ->where('rappels_actifs', true)
            ->whereDate('ouverture', '<=', $maintenant)
            ->with('evenement')
            ->get()
            ->filter(fn (Collecte $collecte): bool => $collecte->contributionReglee());

        foreach ($collectes as $collecte) {
            foreach ($collecte->membresConcernes()->whereNotNull('user_id')->get() as $membre) {
                $type = $this->momentDeNotifier($collecte, $membre, $maintenant);

                if ($type === null) {
                    continue;
                }

                $situation = $this->situation($collecte, $membre, $maintenant);
                $compte = User::query()->find($membre->user_id);

                if ($situation === null || $compte === null) {
                    continue;
                }

                $compte->notify(new RappelProgramme($collecte->titre, $situation['message'], $situation['url'], $situation['bouton']));
                $this->noter($collecte, $membre, 'notification', $type, $maintenant);
                $envoyes++;
            }
        }

        return $envoyes;
    }

    /** Après un versement confirmé : félicitations au membre à jour, et caps franchis par la Daara. */
    public function apresVersement(Paiement $paiement): void
    {
        $collecte = $paiement->collecte;

        if ($collecte === null) {
            return;
        }

        $engagement = $collecte->engagements()->where('membre_id', $paiement->membre_id)->first();
        $compte = User::query()->find($paiement->membre->user_id);

        if ($engagement !== null && $engagement->reste() === 0 && $compte !== null) {
            $compte->notify(new RappelProgramme(
                $collecte->titre,
                'Barakallahou fikoum ! Votre '.$engagement->nature().' pour « '.$collecte->titre.' » est entièrement réglé.',
                route('engagements.index'),
                'Voir mes Sass et Jàyante',
            ));
        }

        $cible = $collecte->cible();

        if (! $collecte->jaugeVisibleParLesMembres() || $cible <= 0) {
            return;
        }

        $apres = $collecte->totalVerse() / $cible * 100;
        $avant = ($collecte->totalVerse() - $paiement->montant) / $cible * 100;

        foreach ([50, 75, 100] as $palier) {
            if ($avant < $palier && $apres >= $palier) {
                $this->annoncerPalier($collecte, $palier);
            }
        }
    }

    private function annoncerPalier(Collecte $collecte, int $palier): void
    {
        $message = $palier >= 100
            ? 'Alhamdoulillah ! La Daara a atteint l\'objectif de « '.$collecte->titre.' ». Merci à tous.'
            : 'Alhamdoulillah ! La Daara a atteint '.$palier.' % de l\'objectif de « '.$collecte->titre.' ».';

        $comptes = User::query()->whereIn('id', $collecte->membresConcernes()->whereNotNull('user_id')->select('user_id'))->get();

        foreach ($comptes as $compte) {
            $compte->notify(new RappelProgramme($collecte->titre, $message, route('engagements.index'), 'Voir la collecte'));
        }
    }

    /**
     * La situation du membre pour ce programme : quoi lui dire, et où l'envoyer.
     *
     * @return array{priorite: int, type: string, message: string, bouton: string, url: string}|null
     */
    private function situation(Collecte $collecte, Membre $membre, ?Carbon $maintenant = null): ?array
    {
        $maintenant ??= now();
        $url = route('engagements.index').'#collecte-'.$collecte->id;
        $evenement = $collecte->evenement;
        $engagement = $this->collectes->engagementDe($collecte, $membre);
        $reste = $engagement?->reste() ?? 0;

        if ($evenement !== null && ($evenement->debut->isSameDay($maintenant->copy()->addDay()) || $evenement->debut->isSameDay($maintenant))) {
            $quand = $evenement->debut->isSameDay($maintenant) ? 'Aujourd\'hui' : 'Demain';

            return [
                'priorite' => 1,
                'type' => 'jour_j',
                'message' => $quand.' : « '.$collecte->titre.' », '.$evenement->debut->format('H\hi').', '.$evenement->lieu.'. N\'oubliez pas votre carte de membre.',
                'bouton' => 'Voir ma carte',
                'url' => route('carte'),
            ];
        }

        if ($engagement === null) {
            return $this->collectes->peutFixer($collecte, $membre) ? [
                'priorite' => 4,
                'type' => 'engagement',
                'message' => 'Vous n\'avez pas encore fixé votre participation pour « '.$collecte->titre.' ».',
                'bouton' => 'Fixer ma participation',
                'url' => $url,
            ] : null;
        }

        if ($reste <= 0) {
            return null;
        }

        $resteTexte = CollecteService::fcfa($reste);

        if ($collecte->estEnRetard()) {
            return [
                'priorite' => 2,
                'type' => 'retard',
                'message' => 'La date limite de « '.$collecte->titre.' » est passée. Il vous reste '.$resteTexte.' à verser.',
                'bouton' => 'Verser maintenant',
                'url' => $url,
            ];
        }

        $jours = $collecte->date_limite !== null ? (int) $maintenant->copy()->startOfDay()->diffInDays($collecte->date_limite, false) : null;

        return [
            'priorite' => $jours !== null && $jours <= 7 ? 3 : 5,
            'type' => 'reste',
            'message' => 'Il vous reste '.$resteTexte.' à verser pour « '.$collecte->titre.' »'
                .($jours !== null ? ($jours === 0 ? ', date limite aujourd\'hui.' : ', date limite dans '.$jours.' jour(s).') : '.'),
            'bouton' => 'Verser maintenant',
            'url' => $url,
        ];
    }

    /** Faut-il notifier ce membre aujourd'hui ? Renvoie le type de moment, ou rien. */
    private function momentDeNotifier(Collecte $collecte, Membre $membre, Carbon $maintenant): ?string
    {
        $evenement = $collecte->evenement;

        if ($evenement !== null && ($evenement->debut->isSameDay($maintenant->copy()->addDay()) || $evenement->debut->isSameDay($maintenant))) {
            return $this->dejaEnvoye($collecte, $membre, 'notification', $maintenant->copy()->startOfDay()) ? null : 'jour_j';
        }

        if ($this->dejaEnvoye($collecte, $membre, 'notification', $maintenant->copy()->subDays(3))) {
            return null;
        }

        $depuisOuverture = (int) $collecte->ouverture->copy()->startOfDay()->diffInDays($maintenant->copy()->startOfDay());
        $engagement = $collecte->engagements()->where('membre_id', $membre->id)->first();

        if ($depuisOuverture === 0) {
            return 'ouverture';
        }

        if ($engagement === null && in_array($depuisOuverture, [3, 7], true)) {
            return 'engagement';
        }

        if ($engagement instanceof Engagement && $engagement->reste() > 0 && $collecte->date_limite !== null) {
            $avantLimite = (int) $maintenant->copy()->startOfDay()->diffInDays($collecte->date_limite, false);

            if (in_array($avantLimite, [14, 7, 3, 1], true)) {
                return 'limite';
            }
        }

        return null;
    }

    private function dejaEnvoye(Collecte $collecte, Membre $membre, string $canal, Carbon $depuis): bool
    {
        return Rappel::query()
            ->where('collecte_id', $collecte->id)
            ->where('membre_id', $membre->id)
            ->where('canal', $canal)
            ->where('envoye_le', '>=', $depuis)
            ->exists();
    }

    private function noter(Collecte $collecte, Membre $membre, string $canal, string $type, ?Carbon $moment = null): void
    {
        Rappel::query()->create([
            'collecte_id' => $collecte->id,
            'membre_id' => $membre->id,
            'canal' => $canal,
            'type' => $type,
            'envoye_le' => $moment ?? now(),
        ]);
    }
}
