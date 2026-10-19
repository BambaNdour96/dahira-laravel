<?php

namespace App\Services;

use App\Enums\MoyenPaiement;
use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Enums\TypeTarif;
use App\Models\CartePhysique;
use App\Models\Cellule;
use App\Models\LotCartes;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\Tarif;
use App\Models\User;
use App\Observers\AvisMembreObserver;
use App\Policies\MembrePolicy;
use App\Support\AntiFraude;
use DomainException;
use Dompdf\Dompdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Les cartes en plastique. Le membre commande et paie sa carte au tarif « Carte physique » (tant qu'il n'est pas fixé :
 * « Bientôt disponible »). Seules les cartes payées entrent dans un lot, préparé par le super administrateur ou le
 * Secrétaire général ; le lot part chez l'imprimeur (PDF et CSV), revient, et chaque carte est remise au membre.
 * La carte du téléphone reste valable dans tous les cas.
 */
final class CartePhysiqueService
{
    public function __construct(private readonly CasquettesService $casquettes) {}

    /** Le prix de la carte en plastique, ou null tant qu'il n'est pas fixé dans les tarifs. */
    public function tarif(): ?int
    {
        $montant = Tarif::query()
            ->where('type', TypeTarif::CartePhysique->value)
            ->whereNull('exercice_id')
            ->where('actif', true)
            ->value('montant');

        return is_numeric($montant) && (int) $montant > 0 ? (int) $montant : null;
    }

    /** La commande en cours (pas encore remise), s'il y en a une. */
    public function commandeEnCours(Membre $membre): ?CartePhysique
    {
        return CartePhysique::query()->with('lot')->where('membre_id', $membre->id)->where('statut', '!=', CartePhysique::REMISE)->latest('id')->first();
    }

    public function paiementEnCours(Membre $membre): ?Paiement
    {
        return $membre->paiements()->where('objet', 'carte_physique')->where('statut', StatutPaiement::Declare->value)->latest('declare_le')->first();
    }

    /** Le membre (ou celui qui gère la fiche de son proche) commande la carte et déclare son paiement Wave. */
    public function commander(Membre $membre, string $reference, ?string $numeroWave, User $declarant): Paiement
    {
        if ($membre->user_id !== $declarant->id && ! MembrePolicy::gereLaFiche($declarant, $membre)) {
            throw new DomainException('Seuls le membre et celui qui gère sa fiche commandent sa carte en plastique.');
        }

        return $this->creerCommande($membre, $reference, $numeroWave, $declarant);
    }

    /** Un autre membre paie la carte en plastique d'un membre qui a son compte (« Payer pour un membre »). */
    public function commanderPourAutrui(Membre $membre, string $reference, ?string $numeroWave, User $payeur): Paiement
    {
        app(PaiementPourAutrui::class)->verifier($membre, $payeur);

        return $this->creerCommande($membre, $reference, $numeroWave, $payeur);
    }

    private function creerCommande(Membre $membre, string $reference, ?string $numeroWave, User $declarant): Paiement
    {
        $montant = $this->verifierCommandable($membre);

        $paiement = $membre->paiements()->create([
            'objet' => 'carte_physique',
            'montant' => $montant,
            'nombre_mois' => 0,
            'moyen' => MoyenPaiement::Wave,
            'reference' => $reference,
            'numero_wave' => $numeroWave,
            'statut' => StatutPaiement::Declare,
            'declare_le' => now(),
            'declare_par' => $declarant->id,
        ]);

        RepresentantFinanceService::prevenir($paiement);

        return $paiement;
    }

    /** La Finance encaisse en espèces une carte en plastique. */
    public function enregistrerEspeces(Membre $membre, ?User $tresorier): Paiement
    {
        $montant = $this->verifierCommandable($membre);
        AntiFraude::verifierValidateur($membre, $tresorier);

        return DB::transaction(function () use ($membre, $montant, $tresorier): Paiement {
            $paiement = $membre->paiements()->create([
                'objet' => 'carte_physique',
                'montant' => $montant,
                'nombre_mois' => 0,
                'moyen' => MoyenPaiement::Especes,
                'statut' => StatutPaiement::Confirme,
                'declare_le' => now(),
                'traite_le' => now(),
                'traite_par' => $tresorier?->id,
            ]);

            $this->apresPaiement($paiement);

            return $paiement;
        });
    }

    /** Le paiement validé, la commande attend le prochain lot. */
    public function apresPaiement(Paiement $paiement): void
    {
        CartePhysique::query()->firstOrCreate(['paiement_id' => $paiement->id], ['membre_id' => $paiement->membre_id]);
    }

    private function verifierCommandable(Membre $membre): int
    {
        $montant = $this->tarif();

        if ($montant === null) {
            throw new DomainException('La carte en plastique sera bientôt disponible.');
        }

        if ($membre->statut !== StatutMembre::Valide || $membre->carteActive()->doesntExist()) {
            throw new DomainException('Seul un membre validé, avec sa carte, commande une carte en plastique.');
        }

        if ($this->commandeEnCours($membre) !== null || $this->paiementEnCours($membre) !== null) {
            throw new DomainException('Une carte en plastique est déjà commandée.');
        }

        return $montant;
    }

    /** Les commandes payées qui attendent un lot, éventuellement pour une cellule. */
    public function enAttente(?Cellule $cellule = null): int
    {
        return CartePhysique::query()
            ->where('statut', CartePhysique::PAYEE)
            ->when($cellule !== null, fn ($query) => $query->whereHas('membre', fn ($membre) => $membre->where('cellule_id', $cellule?->id)))
            ->count();
    }

    /** Prépare un lot avec toutes les cartes payées (de la cellule choisie), sur la carte active de chaque membre. */
    public function preparerLot(?Cellule $cellule, User $auteur): LotCartes
    {
        if (! $auteur->can('cartes.imprimer')) {
            throw new DomainException('Seuls le super administrateur et le Secrétaire général préparent les lots de cartes.');
        }

        return DB::transaction(function () use ($cellule, $auteur): LotCartes {
            $commandes = CartePhysique::query()
                ->with('membre.carteActive')
                ->where('statut', CartePhysique::PAYEE)
                ->when($cellule !== null, fn ($query) => $query->whereHas('membre', fn ($membre) => $membre->where('cellule_id', $cellule?->id)))
                ->lockForUpdate()
                ->get()
                ->filter(fn (CartePhysique $commande): bool => $commande->membre->statut === StatutMembre::Valide && $commande->membre->carteActive !== null);

            if ($commandes->isEmpty()) {
                throw new DomainException('Aucune carte payée n\'attend d\'impression'.($cellule !== null ? ' dans la cellule '.$cellule->nom : '').'.');
            }

            $annee = now()->format('Y');
            $dernier = LotCartes::query()->where('numero', 'like', 'LOT-'.$annee.'-%')->orderByDesc('numero')->value('numero');
            $suivant = is_string($dernier) ? ((int) substr($dernier, 9)) + 1 : 1;

            $lot = LotCartes::query()->create([
                'numero' => 'LOT-'.$annee.'-'.str_pad((string) $suivant, 3, '0', STR_PAD_LEFT),
                'cellule_id' => $cellule?->id,
                'prepare_par' => $auteur->id,
            ]);

            foreach ($commandes as $commande) {
                $commande->update(['statut' => CartePhysique::EN_LOT, 'lot_id' => $lot->id, 'carte_id' => $commande->membre->carteActive?->id]);
            }

            return $lot;
        });
    }

    public function envoyer(LotCartes $lot, User $auteur): void
    {
        $this->verifierLot($lot, LotCartes::PREPARE, $auteur);
        $lot->update(['statut' => LotCartes::ENVOYE, 'envoye_le' => now()]);
    }

    /** Le lot revient de l'imprimeur : chaque membre est prévenu que sa carte est à retirer. */
    public function recevoir(LotCartes $lot, User $auteur): void
    {
        $this->verifierLot($lot, LotCartes::ENVOYE, $auteur);

        DB::transaction(function () use ($lot): void {
            $lot->update(['statut' => LotCartes::RECU, 'recu_le' => now()]);
            $lot->cartes()->update(['statut' => CartePhysique::PRETE]);
        });

        foreach ($lot->cartes()->with(['membre.user', 'membre.responsable.user'])->get() as $commande) {
            AvisMembreObserver::avertir($commande->membre, 'Carte en plastique arrivée', 'Votre carte en plastique est prête : retirez-la auprès de votre Jewrine de cellule.', route('carte'), 'Voir ma carte');
        }
    }

    /** Le Jewrine de cellule (ou le secrétariat) remet la carte au membre. */
    public function remettre(CartePhysique $commande, User $auteur): void
    {
        if ($commande->statut !== CartePhysique::PRETE) {
            throw new DomainException('Cette carte n\'est pas prête à être remise.');
        }

        if (! $auteur->can('cartes.imprimer') && ! $this->casquettes->peut($auteur, 'membres.modifier', $commande->membre->cellule_id)) {
            throw new DomainException('La carte se remet par le Jewrine de la cellule ou le secrétariat.');
        }

        $commande->update(['statut' => CartePhysique::REMISE, 'remise_le' => now(), 'remise_par' => $auteur->id]);
    }

    private function verifierLot(LotCartes $lot, string $attendu, User $auteur): void
    {
        if (! $auteur->can('cartes.imprimer')) {
            throw new DomainException('Seuls le super administrateur et le Secrétaire général suivent les lots de cartes.');
        }

        if ($lot->statut !== $attendu) {
            throw new DomainException('Ce lot est déjà '.mb_strtolower(LotCartes::LIBELLES[$lot->statut]).'.');
        }
    }

    /** Le fichier pour l'imprimeur : cartes à leur taille réelle (85,6 × 54 mm), 10 par page A4, avec repères de coupe. */
    public function pdf(LotCartes $lot): string
    {
        $logo = public_path('images/logo-ndk.png');
        $cartes = $lot->cartes()->with(['membre.cellule', 'carte.exercice'])->get()->map(fn (CartePhysique $commande): array => [
            'membre' => $commande->membre,
            'annee' => $commande->carte?->exercice?->libelle,
            'photo' => $commande->membre->photo_path !== null && Storage::disk('local')->exists($commande->membre->photo_path)
                ? 'data:image/jpeg;base64,'.base64_encode((string) Storage::disk('local')->get($commande->membre->photo_path))
                : null,
            'qr' => $commande->carte !== null ? (new PngWriter)->write(new QrCode(route('verification', $commande->carte->jeton)))->getDataUri() : null,
        ]);

        $html = view('pdf.lot-cartes', [
            'lot' => $lot,
            'pages' => $cartes->chunk(10),
            'logo' => is_file($logo) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logo)) : null,
        ])->render();

        $pdf = new Dompdf(['defaultFont' => 'DejaVu Sans']);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return (string) $pdf->output();
    }

    /** La liste du lot pour l'imprimeur : une ligne par carte. */
    public function csv(LotCartes $lot): string
    {
        $flux = fopen('php://temp', 'r+');

        if ($flux === false) {
            return '';
        }

        fwrite($flux, "\xEF\xBB\xBF");
        fputcsv($flux, ['Lot', 'Matricule', 'Prénom', 'Nom', 'Cellule', 'Année', 'Vérification'], ';', '"', '');

        foreach ($lot->cartes()->with(['membre.cellule', 'carte.exercice'])->get() as $commande) {
            fputcsv($flux, [
                $lot->numero,
                $commande->membre->matricule,
                $commande->membre->prenom,
                $commande->membre->nom,
                $commande->membre->cellule->nom,
                $commande->carte->exercice->libelle ?? '',
                $commande->carte !== null ? route('verification', $commande->carte->jeton) : '',
            ], ';', '"', '');
        }

        rewind($flux);
        $contenu = (string) stream_get_contents($flux);
        fclose($flux);

        return $contenu;
    }
}
