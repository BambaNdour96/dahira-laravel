<?php

namespace App\Services;

use App\Enums\MoyenPaiement;
use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Enums\TypeTarif;
use App\Models\Cotisation;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\Tarif;
use App\Models\User;
use App\Support\AntiFraude;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cotisations mensuelles.
 * - On cotise à partir du mois suivant la validation (et jamais avant la date de démarrage).
 * - Un membre est en retard dès qu'un mois passé reste impayé.
 * - Un paiement couvre d'abord les mois impayés les plus anciens.
 */
final class CotisationService
{
    /** Nombres de mois que l'on peut payer d'un coup. */
    public const CHOIX_MOIS = [1, 2, 3];

    public function montantMensuel(): int
    {
        $montant = Tarif::query()
            ->where('type', TypeTarif::Cotisation->value)
            ->whereNull('exercice_id')
            ->where('actif', true)
            ->value('montant');

        return is_numeric($montant) ? (int) $montant : 1000;
    }

    /** Premier mois dû par ce membre. */
    public function debut(Membre $membre): ?Carbon
    {
        $reference = $membre->valide_le ?? $membre->date_adhesion;

        if ($reference === null) {
            return null;
        }

        $debut = $reference->copy()->startOfMonth()->addMonth();
        $demarrage = Carbon::parse((string) config('dahira.cotisations_debut', '2026-11').'-01')->startOfMonth();

        return $debut->lt($demarrage) ? $demarrage : $debut;
    }

    /** @return array<string, bool> Mois réglés, sous la forme « AAAA-MM ». */
    public function moisRegles(Membre $membre): array
    {
        return Cotisation::query()
            ->where('membre_id', $membre->id)
            ->pluck('mois')
            ->mapWithKeys(fn ($mois): array => [Carbon::parse((string) $mois)->format('Y-m') => true])
            ->all();
    }

    /** @return list<Carbon> Mois passés restés impayés. */
    public function moisEnRetard(Membre $membre): array
    {
        return $this->calculerRetard($membre, $this->moisRegles($membre));
    }

    /**
     * Nombre de mois de retard de chaque membre, calculé en une seule requête.
     *
     * @param  iterable<Membre>  $membres
     * @return array<int, int>
     */
    public function retards(iterable $membres): array
    {
        $liste = collect($membres);

        $reglesParMembre = Cotisation::query()
            ->whereIn('membre_id', $liste->pluck('id'))
            ->get(['membre_id', 'mois'])
            ->groupBy('membre_id');

        $resultat = [];

        foreach ($liste as $membre) {
            $regles = collect($reglesParMembre->get($membre->id, []))
                ->mapWithKeys(fn (Cotisation $cotisation): array => [$cotisation->mois->format('Y-m') => true])
                ->all();

            $resultat[$membre->id] = count($this->calculerRetard($membre, $regles));
        }

        return $resultat;
    }

    /** @return list<Carbon> Les prochains mois à régler, les plus anciens d'abord. */
    public function prochainsMois(Membre $membre, int $nombre): array
    {
        $debut = $this->debut($membre) ?? now()->startOfMonth()->addMonth();
        $regles = $this->moisRegles($membre);
        $resultat = [];

        for ($mois = $debut->copy(); count($resultat) < $nombre; $mois->addMonth()) {
            if (! isset($regles[$mois->format('Y-m')])) {
                $resultat[] = $mois->copy();
            }
        }

        return $resultat;
    }

    /** Dernier mois couvert sans interruption depuis le début. */
    public function regleJusqua(Membre $membre): ?Carbon
    {
        $debut = $this->debut($membre);

        if ($debut === null) {
            return null;
        }

        $regles = $this->moisRegles($membre);
        $dernier = null;

        for ($mois = $debut->copy(); isset($regles[$mois->format('Y-m')]); $mois->addMonth()) {
            $dernier = $mois->copy();
        }

        return $dernier;
    }

    /**
     * Les 12 mois d'une année, avec leur état : regle, retard, courant, avenir ou avant.
     *
     * @return list<array{mois: Carbon, etat: string}>
     */
    public function calendrier(Membre $membre, int $annee): array
    {
        $debut = $this->debut($membre);
        $exonerees = Cotisation::query()
            ->where('membre_id', $membre->id)
            ->where('exoneree', true)
            ->pluck('mois')
            ->mapWithKeys(fn ($mois): array => [Carbon::parse((string) $mois)->format('Y-m') => true])
            ->all();
        $regles = $this->moisRegles($membre);
        $courant = now()->startOfMonth();
        $calendrier = [];

        for ($numero = 1; $numero <= 12; $numero++) {
            $mois = Carbon::parse(sprintf('%04d-%02d-01', $annee, $numero))->startOfMonth();

            $etat = match (true) {
                isset($exonerees[$mois->format('Y-m')]) => 'exonere',
                isset($regles[$mois->format('Y-m')]) => 'regle',
                $debut === null || $mois->lt($debut) => 'avant',
                $mois->lt($courant) => 'retard',
                $mois->equalTo($courant) => 'courant',
                default => 'avenir',
            };

            $calendrier[] = ['mois' => $mois, 'etat' => $etat];
        }

        return $calendrier;
    }

    /** Le super administrateur dispense un membre de plusieurs mois, avec un motif. */
    public function exonerer(Membre $membre, int $nombreMois, string $motif): void
    {
        if ($membre->statut !== StatutMembre::Valide) {
            throw new DomainException('Seuls les membres validés peuvent être dispensés.');
        }

        if ($nombreMois < 1 || $nombreMois > 12) {
            throw new DomainException('On peut dispenser de 1 à 12 mois.');
        }

        if (blank($motif)) {
            throw new DomainException('Le motif de la dispense est obligatoire.');
        }

        DB::transaction(function () use ($membre, $nombreMois, $motif): void {
            foreach ($this->prochainsMois($membre, $nombreMois) as $mois) {
                Cotisation::query()->create([
                    'membre_id' => $membre->id,
                    'mois' => $mois->toDateString(),
                    'montant' => 0,
                    'exoneree' => true,
                    'motif' => $motif,
                ]);
            }
        });
    }

    /** Le membre (ou le proche qui gère sa fiche, le déclarant) déclare un paiement Wave pour plusieurs mois. */
    public function declarer(Membre $membre, string $reference, int $nombreMois, ?string $numeroWave = null, ?User $declarant = null): Paiement
    {
        $this->verifierPeutCotiser($membre, $nombreMois);

        $dejaEnCours = Paiement::query()
            ->where('membre_id', $membre->id)
            ->where('objet', 'cotisation')
            ->where('statut', StatutPaiement::Declare->value)
            ->exists();

        if ($dejaEnCours) {
            throw new DomainException('Un paiement de cotisation est déjà en cours de vérification.');
        }

        $paiement = Paiement::query()->create([
            'membre_id' => $membre->id,
            'objet' => 'cotisation',
            'montant' => $nombreMois * $this->montantMensuel(),
            'nombre_mois' => $nombreMois,
            'moyen' => MoyenPaiement::Wave,
            'reference' => $reference,
            'numero_wave' => $numeroWave,
            'statut' => StatutPaiement::Declare,
            'declare_le' => now(),
            'declare_par' => $declarant->id ?? $membre->user_id,
        ]);

        RepresentantFinanceService::prevenir($paiement);

        return $paiement;
    }

    /** Répartit un paiement confirmé sur les mois impayés les plus anciens. */
    public function affecter(Paiement $paiement): void
    {
        $membre = $paiement->membre;
        $mensuel = intdiv($paiement->montant, max(1, $paiement->nombre_mois));

        foreach ($this->prochainsMois($membre, $paiement->nombre_mois) as $mois) {
            Cotisation::query()->create([
                'membre_id' => $membre->id,
                'mois' => $mois->toDateString(),
                'montant' => $mensuel,
                'paiement_id' => $paiement->id,
            ]);
        }
    }

    /** Le Jewrine finance enregistre une cotisation payée en espèces. */
    public function enregistrerEspeces(Membre $membre, int $nombreMois, ?User $tresorier): Paiement
    {
        $this->verifierPeutCotiser($membre, $nombreMois);
        AntiFraude::verifierValidateur($membre, $tresorier);

        return DB::transaction(function () use ($membre, $nombreMois, $tresorier): Paiement {
            $paiement = Paiement::query()->create([
                'membre_id' => $membre->id,
                'objet' => 'cotisation',
                'montant' => $nombreMois * $this->montantMensuel(),
                'nombre_mois' => $nombreMois,
                'moyen' => MoyenPaiement::Especes,
                'statut' => StatutPaiement::Confirme,
                'declare_le' => now(),
                'traite_le' => now(),
                'traite_par' => $tresorier?->id,
            ]);

            $this->affecter($paiement);

            return $paiement;
        });
    }

    /**
     * @param  array<string, bool>  $regles
     * @return list<Carbon>
     */
    private function calculerRetard(Membre $membre, array $regles): array
    {
        $debut = $this->debut($membre);

        if ($debut === null || $membre->statut !== StatutMembre::Valide) {
            return [];
        }

        $courant = now()->startOfMonth();
        $retard = [];

        for ($mois = $debut->copy(); $mois->lt($courant); $mois->addMonth()) {
            if (! isset($regles[$mois->format('Y-m')])) {
                $retard[] = $mois->copy();
            }
        }

        return $retard;
    }

    private function verifierPeutCotiser(Membre $membre, int $nombreMois): void
    {
        if ($membre->statut !== StatutMembre::Valide) {
            throw new DomainException('Seuls les membres validés cotisent.');
        }

        if (! in_array($nombreMois, self::CHOIX_MOIS, true)) {
            throw new DomainException('Nombre de mois invalide.');
        }
    }
}
