<?php

namespace App\Services;

use App\Enums\ModeCollecte;
use App\Enums\MoyenPaiement;
use App\Enums\StatutCollecte;
use App\Enums\StatutPaiement;
use App\Models\Collecte;
use App\Models\Engagement;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Support\AntiFraude;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Les règles des collectes : qui fixe l'engagement, les versements, et la trace de chaque modification.
 * Jàyante : le membre fixe librement son montant, une fois. Sass : chaque membre concerné est engagé d'office au
 * montant du Sass, peut l'augmenter, et personne ne s'engage en dessous.
 */
final class CollecteService
{
    /**
     * L'engagement du membre ; pour un Sass, créé d'office au montant du Sass.
     */
    public function engagementDe(Collecte $collecte, Membre $membre): ?Engagement
    {
        $engagement = $collecte->engagements()->where('membre_id', $membre->id)->first();

        if ($engagement === null && $collecte->estSass() && $collecte->contributionReglee() && $collecte->concerne($membre)) {
            $engagement = $collecte->engagements()->create([
                'membre_id' => $membre->id,
                'montant' => $collecte->montant,
                'fixe_par' => 'auto',
            ]);
        }

        return $engagement;
    }

    /** Le membre peut-il fixer lui-même son Jàyante ? */
    public function peutFixer(Collecte $collecte, Membre $membre): bool
    {
        return $collecte->concerne($membre)
            && $collecte->mode === ModeCollecte::Jayante
            && $this->engagementDe($collecte, $membre) === null;
    }

    /** Le plancher d'un engagement : le Sass, sinon 1 FCFA. */
    public function minimum(Collecte $collecte): int
    {
        return $collecte->estSass() ? (int) $collecte->montant : 1;
    }

    /** Pour un Sass, le membre peut augmenter son engagement (jamais le baisser). */
    public function peutAugmenter(Collecte $collecte, ?Engagement $engagement): bool
    {
        return $collecte->estSass() && $engagement !== null && $collecte->estOuverte();
    }

    /** Le membre (ou le parent qui gère sa fiche) augmente son Sass ; tracé. */
    public function augmenter(Engagement $engagement, int $montant, User $auteur): Engagement
    {
        if (! $this->peutAugmenter($engagement->collecte, $engagement)) {
            throw new DomainException('Seul un Sass, dans un programme ouvert, s\'augmente par le membre.');
        }

        if ($montant <= $engagement->montant) {
            throw new DomainException('Le nouveau montant doit dépasser '.self::fcfa($engagement->montant).'.');
        }

        $ancien = $engagement->montant;

        DB::transaction(function () use ($engagement, $montant, $ancien, $auteur): void {
            $engagement->update(['montant' => $montant, 'fixe_par' => 'membre']);
            $engagement->historique()->create(['ancien' => $ancien, 'nouveau' => $montant, 'motif' => 'Sass augmenté par le membre', 'auteur_id' => $auteur->id]);
        });

        return $engagement;
    }

    /**
     * Engage d'office au Sass tous les membres concernés, et remonte au Sass ceux qui étaient en dessous ; un engagement
     * au Sass précédent, jamais augmenté par le membre, suit le nouveau montant. Tout est tracé.
     */
    public function engagerAuSass(Collecte $collecte, ?User $auteur): int
    {
        if (! $collecte->estSass() || ! $collecte->contributionReglee()) {
            return 0;
        }

        $sass = (int) $collecte->montant;
        $nombre = 0;

        DB::transaction(function () use ($collecte, $sass, $auteur, &$nombre): void {
            $existants = $collecte->engagements()->get()->keyBy('membre_id');

            foreach ($collecte->membresConcernes()->get() as $membre) {
                $engagement = $existants->get($membre->id);

                if ($engagement === null) {
                    $collecte->engagements()->create(['membre_id' => $membre->id, 'montant' => $sass, 'fixe_par' => 'auto']);
                    $nombre++;
                } elseif ($engagement->montant < $sass || ($engagement->fixe_par === 'auto' && $engagement->montant !== $sass)) {
                    $ancien = $engagement->montant;
                    $engagement->update(['montant' => $sass]);
                    $engagement->historique()->create(['ancien' => $ancien, 'nouveau' => $sass, 'motif' => 'Le Sass passe à '.self::fcfa($sass), 'auteur_id' => $auteur?->id]);
                    $nombre++;
                }
            }
        });

        return $nombre;
    }

    /** Le membre fixe son engagement, une seule fois ; ensuite, seul le bureau peut le modifier. */
    public function fixerParMembre(Collecte $collecte, Membre $membre, int $montant): Engagement
    {
        if ($collecte->estSass()) {
            throw new DomainException('Pour un Sass, vous êtes engagé d\'office au montant du Sass ; vous pouvez l\'augmenter.');
        }

        if (! $this->peutFixer($collecte, $membre)) {
            throw new DomainException('Votre Jàyante est déjà fixé. Pour le modifier, adressez-vous au bureau.');
        }

        $minimum = $this->minimum($collecte);

        if ($montant < $minimum) {
            throw new DomainException('Le montant minimum est de '.self::fcfa($minimum).'.');
        }

        return DB::transaction(function () use ($collecte, $membre, $montant): Engagement {
            $engagement = $collecte->engagements()->create([
                'membre_id' => $membre->id,
                'montant' => $montant,
                'fixe_par' => 'membre',
            ]);

            $engagement->historique()->create([
                'ancien' => null,
                'nouveau' => $montant,
                'motif' => 'Engagement fixé par le membre',
                'auteur_id' => $membre->user_id,
            ]);

            return $engagement;
        });
    }

    /** Le bureau modifie un engagement : le motif est obligatoire, et tout est tracé. */
    public function modifier(Engagement $engagement, int $nouveau, string $motif, User $auteur): Engagement
    {
        if (trim($motif) === '') {
            throw new DomainException('Le motif est obligatoire.');
        }

        if ($nouveau < 1) {
            throw new DomainException('Le montant doit être positif.');
        }

        if ($nouveau < $this->minimum($engagement->collecte)) {
            throw new DomainException('Personne ne s\'engage en dessous du Sass ('.self::fcfa($this->minimum($engagement->collecte)).').');
        }

        $ancien = $engagement->montant;

        if ($ancien === $nouveau) {
            return $engagement;
        }

        DB::transaction(function () use ($engagement, $nouveau, $ancien, $motif, $auteur): void {
            $engagement->update(['montant' => $nouveau, 'fixe_par' => 'bureau']);

            $engagement->historique()->create([
                'ancien' => $ancien,
                'nouveau' => $nouveau,
                'motif' => $motif,
                'auteur_id' => $auteur->id,
            ]);
        });

        $this->prevenir($engagement, $ancien);

        return $engagement;
    }

    /**
     * Versement Wave déclaré par le membre, à confirmer par le Jewrine finance. Le déclarant peut être le membre qui
     * gère sa fiche, ou un autre membre qui paie pour lui : celui-ci ne voit jamais les montants du bénéficiaire.
     */
    public function declarer(Collecte $collecte, Membre $membre, int $montant, string $reference, ?string $numeroWave = null, ?User $declarant = null, MoyenPaiement $moyen = MoyenPaiement::Wave): Paiement
    {
        $engagement = $this->engagementDe($collecte, $membre);
        $pourUnAutre = PaiementPourAutrui::estUnTiers($declarant, $membre);

        if ($engagement === null) {
            throw new DomainException($pourUnAutre ? 'Ce membre n\'a pas encore fixé son Jàyante pour cette collecte.' : 'Fixez d\'abord votre Jàyante.');
        }

        if (! $collecte->estOuverte()) {
            throw new DomainException('Cette collecte n\'accepte plus de versements.');
        }

        if ($montant < 1) {
            throw new DomainException('Indiquez le montant versé.');
        }

        // On peut verser petit à petit, mais jamais plus que ce qui reste (versements en vérification compris).
        $disponible = $engagement->reste() - $this->enVerification($collecte, $membre);

        if ($disponible <= 0 || $montant > $disponible) {
            throw new DomainException(match (true) {
                $pourUnAutre => 'Ce montant dépasse ce qu\'il reste à verser à ce membre. Demandez-lui le montant exact.',
                $disponible <= 0 => 'Vos versements en vérification couvrent déjà tout votre '.$engagement->nature().'.',
                default => 'Ce montant dépasse ce qu\'il vous reste à verser : '.self::fcfa($disponible).'.',
            });
        }

        $paiement = $membre->paiements()->create([
            'collecte_id' => $collecte->id,
            'objet' => 'collecte',
            'montant' => $montant,
            'nombre_mois' => 0,
            'moyen' => $moyen,
            'reference' => $reference,
            'numero_wave' => $numeroWave,
            'statut' => StatutPaiement::Declare,
            'declare_le' => now(),
            'declare_par' => $declarant->id ?? $membre->user_id,
        ]);

        RepresentantFinanceService::prevenir($paiement);

        return $paiement;
    }

    /** Versement en espèces, enregistré directement par le Jewrine finance. */
    public function enregistrerEspeces(Collecte $collecte, Membre $membre, int $montant, ?User $tresorier): Paiement
    {
        if ($montant < 1) {
            throw new DomainException('Indiquez le montant reçu.');
        }

        AntiFraude::verifierValidateur($membre, $tresorier);
        $this->engagementDe($collecte, $membre);

        $paiement = $membre->paiements()->create([
            'collecte_id' => $collecte->id,
            'objet' => 'collecte',
            'montant' => $montant,
            'nombre_mois' => 0,
            'moyen' => MoyenPaiement::Especes,
            'statut' => StatutPaiement::Confirme,
            'declare_le' => now(),
            'traite_le' => now(),
            'traite_par' => $tresorier?->id,
        ]);

        app(RappelService::class)->apresVersement($paiement);

        return $paiement;
    }

    /** Total des versements déclarés, en attente de vérification par le Jewrine finance. */
    public function enVerification(Collecte $collecte, Membre $membre): int
    {
        return (int) $collecte->paiements()
            ->where('membre_id', $membre->id)
            ->where('statut', StatutPaiement::Declare->value)
            ->sum('montant');
    }

    public function versementEnCours(Collecte $collecte, Membre $membre): ?Paiement
    {
        return $collecte->paiements()
            ->where('membre_id', $membre->id)
            ->where('statut', StatutPaiement::Declare->value)
            ->latest('declare_le')
            ->first();
    }

    /**
     * Les chiffres du tableau de bord d'une collecte.
     *
     * @return array{concernes: int, engages: int, a_jour: int, en_retard: int, total_engage: int, total_verse: int, cible: int, taux: int}
     */
    public function statistiques(Collecte $collecte): array
    {
        $engagements = $collecte->engagements()->with('collecte')->get();
        $aJour = $engagements->filter(fn (Engagement $e): bool => $e->reste() === 0)->count();

        return [
            'concernes' => $collecte->membresConcernes()->count(),
            'engages' => $engagements->count(),
            'a_jour' => $aJour,
            'en_retard' => $collecte->estEnRetard() ? $engagements->count() - $aJour : 0,
            'total_engage' => (int) $engagements->sum('montant'),
            'total_verse' => $collecte->totalVerse(),
            'cible' => $collecte->cible(),
            'taux' => $collecte->taux(),
        ];
    }

    /**
     * Les collectes ouvertes qui concernent le membre, pour l'accueil.
     *
     * @return Collection<int, array{titre: string, montant: int|null, verse: int, taux: int}>
     */
    public function resumePour(Membre $membre): Collection
    {
        return $this->ouvertesPour($membre)->map(function (Collecte $collecte) use ($membre): array {
            $engagement = $this->engagementDe($collecte, $membre);

            return [
                'titre' => $collecte->titre,
                'montant' => $engagement?->montant,
                'verse' => $engagement?->verse() ?? 0,
                'taux' => $engagement?->taux() ?? 0,
            ];
        })->values();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Collecte> */
    public function ouvertesPour(Membre $membre): \Illuminate\Database\Eloquent\Collection
    {
        return Collecte::query()
            ->where('statut', StatutCollecte::Ouverte->value)
            ->whereDate('ouverture', '<=', now())
            ->where(fn ($requete) => $requete->whereNull('cellule_id')->orWhere('cellule_id', $membre->cellule_id))
            ->orderByRaw('date_limite is null')
            ->orderBy('date_limite')
            ->get()
            ->filter(fn (Collecte $collecte): bool => $collecte->contributionReglee() && $collecte->concerne($membre))
            ->values();
    }

    /**
     * Les versements confirmés d'une collecte, par cellule : chacun compte pour la cellule du moment où il a été fait.
     *
     * @return array<string, int>
     */
    public function versesParCellule(Collecte $collecte): array
    {
        return $collecte->paiements()
            ->with(['cellule', 'membre.cellule'])
            ->where('statut', StatutPaiement::Confirme->value)
            ->get()
            ->groupBy(fn (Paiement $paiement): string => ($paiement->cellule ?? $paiement->membre->cellule)->nom)
            ->map(fn ($paiements): int => (int) $paiements->sum('montant'))
            ->all();
    }

    /**
     * L'avancement cellule par cellule : membres concernés, engagés, à jour, montants et taux.
     *
     * @return array<string, array{concernes: int, engages: int, a_jour: int, engage: int, verse: int, taux: int}>
     */
    public function parCellule(Collecte $collecte): array
    {
        $resultat = [];
        $versesParCellule = $this->versesParCellule($collecte);

        foreach ($collecte->membresConcernes()->with('cellule')->get()->groupBy(fn (Membre $membre): string => $membre->cellule->nom) as $nom => $membres) {
            $engagements = $collecte->engagements()->with('collecte')->whereIn('membre_id', $membres->modelKeys())->get();
            $engage = (int) $engagements->sum('montant');
            $verse = $versesParCellule[(string) $nom] ?? 0;

            $resultat[(string) $nom] = [
                'concernes' => $membres->count(),
                'engages' => $engagements->count(),
                'a_jour' => $engagements->filter(fn (Engagement $engagement): bool => $engagement->reste() === 0)->count(),
                'engage' => $engage,
                'verse' => $verse,
                'taux' => $engage > 0 ? (int) min(100, round($verse / $engage * 100)) : 0,
            ];
        }

        ksort($resultat);

        return $resultat;
    }

    private function prevenir(Engagement $engagement, ?int $ancien): void
    {
        $compte = User::query()->find($engagement->membre->user_id);

        if ($compte === null) {
            return;
        }

        $titre = $engagement->collecte->titre;
        $message = $ancien === null
            ? 'Le bureau a fixé votre engagement pour « '.$titre.' » à '.self::fcfa($engagement->montant).'.'
            : 'Le bureau a modifié votre engagement pour « '.$titre.' » : '.self::fcfa($ancien).' → '.self::fcfa($engagement->montant).'.';

        $compte->notify(new NotificationMembre('Votre engagement', $message, route('engagements.index'), 'Voir mes engagements'));
    }

    public static function fcfa(int $montant): string
    {
        return number_format($montant, 0, ',', ' ').' FCFA';
    }
}
